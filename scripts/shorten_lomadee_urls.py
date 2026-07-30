#!/usr/bin/env python3
"""
Script to query Lomadee products, shorten their URLs using the Lomadee API,
and persist the shortened URL in PostgreSQL.
"""

import os
import sys
import json
import time
import argparse
import urllib.request
import psycopg2

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

def shorten_url(url, org_id, api_key):
    """
    Calls the Lomadee URL shortener API.
    Returns the shortened URL string if successful, else None.
    """
    endpoint = "https://api.lomadee.com.br/affiliate/shortener/url"
    headers = {
        "content-type": "application/json",
        "x-api-key": api_key
    }
    
    # Fallback to default user/product org ID if org_id is not a valid UUID or empty
    if not org_id:
        org_id = "6ff2699e-ceaa-4fad-a58a-8b91f885485f"
        
    payload = {
        "organizationId": str(org_id),
        "url": url,
        "type": "Custom"
    }
    
    req = urllib.request.Request(
        endpoint,
        data=json.dumps(payload).encode("utf-8"),
        headers=headers,
        method="POST"
    )
    
    try:
        with urllib.request.urlopen(req) as res:
            if res.status != 201 and res.status != 200:
                print(f"API Error: Server returned status {res.status} for URL {url}")
                return None
            body = res.read().decode("utf-8")
            parsed = json.loads(body)
            if isinstance(parsed, list) and len(parsed) > 0:
                short_urls = parsed[0].get("shortUrls", [])
                if short_urls:
                    return short_urls[0]
            print(f"API returned unexpected JSON structure for URL {url}: {body}")
            return None
    except Exception as e:
        print(f"Failed to shorten URL {url}: {e}")
        return None

def main():
    parser = argparse.ArgumentParser(description="Shorten Lomadee product URLs and persist them to PostgreSQL")
    parser.add_argument("--force-refresh", action="store_true", help="Force shortening of already shortened URLs")
    args = parser.parse_args()

    api_key = "lmd_dev_q3f4dLZQHM_b4fU9f4ATV3kL59J64WhupokJ5tSK6Ib"

    # Setup database connection
    db_conn = get_postgres_connection()
    db_cur = db_conn.cursor()

    try:
        # Step 1: Ensure shortened_url column exists in lomadee.products
        print("Ensuring 'shortened_url' column exists in lomadee.products...")
        db_cur.execute("ALTER TABLE lomadee.products ADD COLUMN IF NOT EXISTS shortened_url TEXT;")
        db_conn.commit()

        # Step 2: Query products
        # We run the exact select query requested, but we select Products.organization_id and Products.shortened_url too
        query = """
        SELECT distinct
          "lomadee"."categories"."name" AS "name",
          "Product Categories"."category_id" AS "Product Categories__category_id",
          "Products"."available" AS "Products__available",
          "Products"._id AS "Products__id",
          "Products"."name" AS "Products__name",
          "Products"."url" AS "Products__url",
          "Products"."metadata" AS "Products__metadata",
          "Products"."organization_id" AS "Products__organization_id",
          "Products"."shortened_url" AS "Products__shortened_url",
          "Product Options"."ean" AS "Product Options__ean",
          "Product Options"."price" AS "Product Options__price",
          "Product Options"."list_price" AS "Product Options__list_price",
          "Product Options"."metadata" AS "Product Options__metadata"
        FROM
          "lomadee"."categories"
        LEFT JOIN "lomadee"."product_categories" AS "Product Categories" 
          ON "lomadee"."categories"."id" = "Product Categories"."category_id"
        LEFT JOIN "lomadee"."products" AS "Products" 
          ON "Product Categories"."product_id" = "Products"."_id"
        LEFT JOIN "lomadee"."product_options" AS "Product Options" 
          ON "Products"."_id" = "Product Options"."product_id"
        """
        
        print("Fetching products to shorten...")
        db_cur.execute(query)
        rows = db_cur.fetchall()
        cols = [desc[0] for desc in db_cur.description]
        print(f"Total rows fetched: {len(rows)}")

        # Step 3: De-duplicate products by Products__id
        distinct_products = {}
        for row in rows:
            p = dict(zip(cols, row))
            p_id = p.get("Products__id")
            if not p_id:
                continue
            if p_id not in distinct_products:
                distinct_products[p_id] = {
                    "id": p_id,
                    "url": p.get("Products__url"),
                    "org_id": p.get("Products__organization_id"),
                    "shortened_url": p.get("Products__shortened_url")
                }

        print(f"Distinct products found: {len(distinct_products)}")

        # Step 4: Shorten and update
        success_count = 0
        skipped_count = 0
        failed_count = 0

        for p_id, p_info in distinct_products.items():
            url = p_info["url"]
            org_id = p_info["org_id"]
            existing_short = p_info["shortened_url"]

            if not url:
                skipped_count += 1
                continue

            # Check if already shortened
            if existing_short and not args.force_refresh:
                skipped_count += 1
                continue

            print(f"Shortening url for product {p_id}: {url}")
            short = shorten_url(url, org_id, api_key)

            if short:
                db_cur.execute(
                    "UPDATE lomadee.products SET shortened_url = %s WHERE _id = %s;",
                    (short, p_id)
                )
                db_conn.commit()
                print(f"Successfully shortened: {url} -> {short}")
                success_count += 1
            else:
                failed_count += 1
                print(f"Failed to shorten URL for product {p_id}")

            # Politeness delay to avoid hitting rate limits
            time.sleep(0.5)

        print("\n--- URL SHORTENER SUMMARY ---")
        print(f"Success: {success_count}")
        print(f"Skipped: {skipped_count}")
        print(f"Failed:  {failed_count}")

    except Exception as e:
        db_conn.rollback()
        print(f"CRITICAL ERROR in shortener script: {e}")
        sys.exit(1)
    finally:
        db_cur.close()
        db_conn.close()

if __name__ == '__main__':
    main()
