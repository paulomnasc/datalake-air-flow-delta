#!/usr/bin/env python3
"""
Script to load and normalize Lomadee product data from MinIO S3 (Parquet)
to PostgreSQL datalake_bi under the 'lomadee' schema.
"""

import os
import sys
import json
import duckdb
import psycopg2
from datetime import datetime

def parse_date(date_str):
    if not date_str:
        return None
    try:
        # Standardize ISO8601 string timezone formatting for python compatibility
        clean_str = date_str.replace('Z', '+00:00')
        return datetime.fromisoformat(clean_str)
    except Exception:
        return None

def parse_json_list(json_str):
    if not json_str:
        return []
    try:
        data = json.loads(json_str)
        if isinstance(data, list):
            return data
        return [data]
    except Exception:
        return []

def get_postgres_connection():
    # Try connecting using container hostname (useful inside Airflow/docker network)
    try:
        conn = psycopg2.connect(
            host="postgres-bi",
            port=5432,
            database="datalake_bi",
            user="pbi_user",
            password="pbi_password"
        )
        print("Connected to PostgreSQL via container network (postgres-bi:5432)")
        return conn
    except Exception:
        pass

    # Fallback to localhost (useful when running from the host machine directly)
    try:
        conn = psycopg2.connect(
            host="localhost",
            port=25433,
            database="datalake_bi",
            user="pbi_user",
            password="pbi_password"
        )
        print("Connected to PostgreSQL via local host fallback (localhost:25433)")
        return conn
    except Exception as e:
        print(f"CRITICAL: Failed to connect to PostgreSQL database: {e}")
        sys.exit(1)

def get_duckdb_connection():
    con = duckdb.connect()
    con.execute("INSTALL httpfs;")
    con.execute("LOAD httpfs;")
    
    # Configure S3 endpoint
    # Determine endpoint depending on where we are executing from.
    # Check port 29002 (mapped on host), otherwise try container endpoint http://minio:9000
    try:
        con.execute("SET s3_endpoint='localhost:29002';")
        con.execute("SET s3_access_key_id='admin';")
        con.execute("SET s3_secret_access_key='admin123';")
        con.execute("SET s3_use_ssl=false;")
        con.execute("SET s3_url_style='path';")
        
        # Test query to check if localhost works
        con.execute("SELECT 1 FROM read_parquet('s3://paulomnasc-558/bronze/lomadee-products/*.parquet', union_by_name=True) LIMIT 1")
        print("Connected to MinIO via localhost:29002")
        return con
    except Exception:
        pass

    try:
        con.execute("SET s3_endpoint='minio:9000';")
        con.execute("SET s3_access_key_id='admin';")
        con.execute("SET s3_secret_access_key='admin123';")
        con.execute("SET s3_use_ssl=false;")
        con.execute("SET s3_url_style='path';")
        con.execute("SELECT 1 FROM read_parquet('s3://paulomnasc-558/bronze/lomadee-products/*.parquet', union_by_name=True) LIMIT 1")
        print("Connected to MinIO via container network (minio:9000)")
        return con
    except Exception as e:
        print(f"CRITICAL: Failed to connect to S3 MinIO: {e}")
        sys.exit(1)

def main():
    print("Starting Lomadee Products Normalization & Loading...")
    
    # Setup connections
    db_conn = get_postgres_connection()
    duck_conn = get_duckdb_connection()
    
    db_cur = db_conn.cursor()
    
    query = """
        SELECT 
            _id, id, organizationId, __v, available, 
            createdAt, updatedAt, lastUpdate, name, 
            description, url, images, categories, options 
        FROM read_parquet('s3://paulomnasc-558/bronze/lomadee-products/*.parquet', union_by_name=True)
    """
    
    print("Reading bronze product data from S3 parquet...")
    products = duck_conn.execute(query).fetchall()
    cols = [desc[0] for desc in duck_conn.description]
    
    print(f"Loaded {len(products)} records from S3. Beginning normalization...")
    
    count_products = 0
    count_categories = 0
    count_options = 0
    
    try:
        for idx, row in enumerate(products):
            p = dict(zip(cols, row))
            
            product_id = p['_id']
            if not product_id:
                continue
                
            # Parse dates
            created_at = parse_date(p.get('createdAt'))
            updated_at = parse_date(p.get('updatedAt'))
            last_update = parse_date(p.get('lastUpdate'))
            
            # Serialize generic metadata or save empty dict
            prod_metadata = p.get('metadata')
            if not prod_metadata:
                prod_metadata_json = '{}'
            elif isinstance(prod_metadata, str):
                prod_metadata_json = prod_metadata
            else:
                prod_metadata_json = json.dumps(prod_metadata)
            
            # 1. Insert or update core product info
            db_cur.execute("""
                INSERT INTO lomadee.products (
                    _id, id, organization_id, version_v, available, 
                    created_at, updated_at, last_update, name, 
                    description, url, metadata
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON CONFLICT (_id) DO UPDATE SET
                    id = EXCLUDED.id,
                    organization_id = EXCLUDED.organization_id,
                    version_v = EXCLUDED.version_v,
                    available = EXCLUDED.available,
                    created_at = EXCLUDED.created_at,
                    updated_at = EXCLUDED.updated_at,
                    last_update = EXCLUDED.last_update,
                    name = EXCLUDED.name,
                    description = EXCLUDED.description,
                    url = EXCLUDED.url,
                    metadata = EXCLUDED.metadata;
            """, (
                product_id,
                p.get('id'),
                p.get('organizationId'),
                p.get('__v', 0),
                p.get('available', True),
                created_at,
                updated_at,
                last_update,
                p.get('name'),
                p.get('description'),
                p.get('url'),
                prod_metadata_json
            ))
            count_products += 1
            
            # 2. Process Categories
            categories = parse_json_list(p.get('categories'))
            for cat in categories:
                cat_id = cat.get('id')
                cat_name = cat.get('name')
                if cat_id is not None and cat_name:
                    db_cur.execute("""
                        INSERT INTO lomadee.categories (id, name)
                        VALUES (%s, %s)
                        ON CONFLICT (id) DO UPDATE SET
                            name = EXCLUDED.name;
                    """, (cat_id, cat_name))
                    count_categories += 1
                    
                    db_cur.execute("""
                        INSERT INTO lomadee.product_categories (product_id, category_id)
                        VALUES (%s, %s)
                        ON CONFLICT (product_id, category_id) DO NOTHING;
                    """, (product_id, cat_id))

            # 3. Process Product Images
            images = parse_json_list(p.get('images'))
            # Clear old images to prevent orphans/duplicates
            db_cur.execute("DELETE FROM lomadee.product_images WHERE product_id = %s;", (product_id,))
            for img in images:
                img_url = img.get('url')
                if img_url:
                    db_cur.execute("""
                        INSERT INTO lomadee.product_images (product_id, url)
                        VALUES (%s, %s);
                    """, (product_id, img_url))

            # 4. Process Product Options
            options = parse_json_list(p.get('options'))
            for opt in options:
                opt_id = opt.get('id')
                if not opt_id:
                    continue
                
                # Pricing
                pricing = opt.get('pricing', [])
                price = None
                list_price = None
                if pricing and isinstance(pricing, list):
                    first_price = pricing[0]
                    price = first_price.get('price')
                    list_price = first_price.get('listPrice')
                
                # Stocks
                stocks = opt.get('stocks', [])
                stock_val = None
                if stocks and isinstance(stocks, list):
                    stock_val = stocks[0].get('value')
                
                # Option metadata
                opt_meta = opt.get('metadata')
                if not opt_meta:
                    opt_meta_json = '{}'
                elif isinstance(opt_meta, str):
                    opt_meta_json = opt_meta
                else:
                    opt_meta_json = json.dumps(opt_meta)
                
                db_cur.execute("""
                    INSERT INTO lomadee.product_options (
                        id, product_id, ean, name, available, 
                        seller, price, list_price, stock, metadata
                    ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                    ON CONFLICT (id) DO UPDATE SET
                        product_id = EXCLUDED.product_id,
                        ean = EXCLUDED.ean,
                        name = EXCLUDED.name,
                        available = EXCLUDED.available,
                        seller = EXCLUDED.seller,
                        price = EXCLUDED.price,
                        list_price = EXCLUDED.list_price,
                        stock = EXCLUDED.stock,
                        metadata = EXCLUDED.metadata;
                """, (
                    str(opt_id),
                    product_id,
                    opt.get('ean'),
                    opt.get('name'),
                    opt.get('available', True),
                    opt.get('seller'),
                    price,
                    list_price,
                    stock_val,
                    opt_meta_json
                ))
                count_options += 1
                
                # Option Images
                opt_images = opt.get('images', [])
                db_cur.execute("DELETE FROM lomadee.product_option_images WHERE option_id = %s;", (str(opt_id),))
                for o_img in opt_images:
                    o_img_url = o_img.get('url')
                    if o_img_url:
                        db_cur.execute("""
                            INSERT INTO lomadee.product_option_images (option_id, url)
                            VALUES (%s, %s);
                        """, (str(opt_id), o_img_url))

        # Commit everything inside a transaction
        db_conn.commit()
        print("\n--- INGESTION SUMMARY ---")
        print(f"Successfully processed and upserted:")
        print(f" - {count_products} Products")
        print(f" - {count_options} Product Options (Offers)")
        print(f"Normalized Category entities created/updated.")
        
    except Exception as e:
        db_conn.rollback()
        print(f"ERROR: An error occurred during transaction. Changes rolled back. Details: {e}")
    finally:
        db_cur.close()
        db_conn.close()
        duck_conn.close()

if __name__ == '__main__':
    main()
