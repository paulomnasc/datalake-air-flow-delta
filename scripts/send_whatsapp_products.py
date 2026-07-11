#!/usr/bin/env python3
"""
Script to query Lomadee products from PostgreSQL and send them via WhatsApp Cloud API.
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

def send_whatsapp_message(phone_number_id, token, recipient, message_type, template_name, template_lang, product):
    """
    Sends a WhatsApp message via the Meta Graph API.
    """
    url = f"https://graph.facebook.com/v25.0/{phone_number_id}/messages"
    
    headers = {
        "Authorization": f"Bearer {token}",
        "Content-Type": "application/json"
    }

    # Clean description for display: strip html tags if they exist
    desc = product.get("description") or ""
    # simple HTML tag removal
    import re
    desc_clean = re.sub(r'<[^>]+>', '', desc)
    # limit description length so it doesn't exceed WhatsApp limit or look bad
    if len(desc_clean) > 200:
        desc_clean = desc_clean[:197] + "..."
    
    name = product.get("name") or "Produto"
    short_url = product.get("shortened_url") or ""

    if message_type == "text":
        body_content = f"*{name}*\n{desc_clean}\n\nConfira aqui: {short_url}"
        payload = {
            "messaging_product": "whatsapp",
            "recipient_type": "individual",
            "to": recipient,
            "type": "text",
            "text": {
                "preview_url": True,
                "body": body_content
            }
        }
    else: # template
        payload = {
            "messaging_product": "whatsapp",
            "to": recipient,
            "type": "template",
            "template": {
                "name": template_name,
                "language": {
                    "code": template_lang
                }
            }
        }
        
        # Meta's default hello_world template does not accept parameters.
        # For other custom templates, we send the description and shortened link as parameters.
        if template_name != "hello_world":
            payload["template"]["components"] = [
                {
                    "type": "body",
                    "parameters": [
                        {
                            "type": "text",
                            "text": name[:1024] # Limit payload size per param
                        },
                        {
                            "type": "text",
                            "text": desc_clean[:1024]
                        },
                        {
                            "type": "text",
                            "text": short_url[:1024]
                        }
                    ]
                }
            ]

    req = urllib.request.Request(
        url,
        data=json.dumps(payload).encode("utf-8"),
        headers=headers,
        method="POST"
    )

    try:
        with urllib.request.urlopen(req) as res:
            body = res.read().decode("utf-8")
            parsed = json.loads(body)
            print(f"Successfully sent message for product '{name}' to {recipient}. API Response: {parsed}")
            return True
    except urllib.error.HTTPError as e:
        err_body = e.read().decode("utf-8")
        print(f"HTTP Error {e.code} sending message for product '{name}': {err_body}")
        return False
    except Exception as e:
        print(f"Generic error sending message for product '{name}': {e}")
        return False

def main():
    parser = argparse.ArgumentParser(description="Send Lomadee products via WhatsApp Cloud API")
    parser.add_argument("--max-messages", type=int, default=int(os.environ.get("WHATSAPP_MAX_MESSAGES", 5)),
                        help="Maximum number of messages to send in this execution")
    args = parser.parse_args()

    # Get configuration from Environment Variables
    token = os.environ.get("WHATSAPP_ACCESS_TOKEN", "EAAYWHHdGTbMBRZBKUjGJ6If9wv0UXfedkwZBdMcacHCbVoSZCuOalTkDf6d5DuGVHDtPVLY6jWZCq4BQa0teZB66ldYWcuWhdcPSXYQtXPd0tczLp48Ul2wp1kaZCqhLNepc43k3NkSUgoEbrYAXqH5ZAc9XOJ6PGrdgA18uDiXOOdD4rTlddg02qE4utP0ZB3ZB4yOAUEnsYFjSnepeheUm9Eg33byZCSPZBZCZA0EebSSO508pUlhcIV0hg2PINo2cWUCDR1yYxeHVJuPPTZB5Tfy0tlZCLMAu7kHZBsy4sC9bsgZDZD")
    phone_number_id = os.environ.get("WHATSAPP_PHONE_NUMBER_ID", "1261026707084295")
    recipient = os.environ.get("WHATSAPP_RECIPIENT_NUMBER", "556191117028")
    message_type = os.environ.get("WHATSAPP_MESSAGE_TYPE", "template").lower()
    template_name = os.environ.get("WHATSAPP_TEMPLATE_NAME", "hello_world")
    template_lang = os.environ.get("WHATSAPP_TEMPLATE_LANGUAGE", "en_US")

    print("--- WhatsApp Configuration ---")
    print(f"Phone Number ID: {phone_number_id}")
    print(f"Recipient Number: {recipient}")
    print(f"Message Type: {message_type}")
    print(f"Template Name: {template_name}")
    print(f"Template Language: {template_lang}")
    print(f"Max Messages to Send: {args.max_messages}")
    print("------------------------------")

    # Connect to database
    db_conn = get_postgres_connection()
    db_cur = db_conn.cursor()

    try:
        # Step 1: Query products with shortened URLs
        query = """
        SELECT DISTINCT _id, name, description, shortened_url
        FROM lomadee.products
        WHERE shortened_url IS NOT NULL AND shortened_url <> ''
        ORDER BY _id
        LIMIT %s;
        """
        
        db_cur.execute(query, (args.max_messages,))
        rows = db_cur.fetchall()
        print(f"Found {len(rows)} products with shortened URLs to send.")

        sent_count = 0
        failed_count = 0

        for row in rows:
            product = {
                "id": row[0],
                "name": row[1],
                "description": row[2],
                "shortened_url": row[3]
            }

            print(f"Sending message for product ID: {product['id']}...")
            success = send_whatsapp_message(
                phone_number_id=phone_number_id,
                token=token,
                recipient=recipient,
                message_type=message_type,
                template_name=template_name,
                template_lang=template_lang,
                product=product
            )

            if success:
                sent_count += 1
            else:
                failed_count += 1

            # Be polite to rate limits
            time.sleep(1.0)

        print("\n--- WHATSAPP NOTIFICATION SUMMARY ---")
        print(f"Total Sent:   {sent_count}")
        print(f"Total Failed: {failed_count}")

    except Exception as e:
        print(f"CRITICAL ERROR: {e}")
        sys.exit(1)
    finally:
        db_cur.close()
        db_conn.close()

if __name__ == '__main__':
    main()
