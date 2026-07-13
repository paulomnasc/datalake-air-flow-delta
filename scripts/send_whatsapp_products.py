#!/usr/bin/env python3
"""
Script to query Lomadee products from PostgreSQL and send them via WhatsApp APIs.
Supports both Meta Cloud API and generic third-party HTTP endpoints (useful for sending to groups).
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

def send_meta_whatsapp_message(phone_number_id, token, recipient, message_type, template_name, template_lang, product):
    """
    Sends a WhatsApp message via the official Meta Graph API.
    """
    url = f"https://graph.facebook.com/v25.0/{phone_number_id}/messages"
    
    headers = {
        "Authorization": f"Bearer {token}",
        "Content-Type": "application/json"
    }

    # Clean description for display: strip html tags if they exist
    desc = product.get("description") or ""
    import re
    desc_clean = re.sub(r'<[^>]+>', '', desc)
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
                            "text": name[:1024]
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
            print(f"Successfully sent Meta message for '{name}' to {recipient}. API Response: {parsed}")
            return True
    except urllib.error.HTTPError as e:
        err_body = e.read().decode("utf-8")
        print(f"HTTP Error {e.code} sending Meta message for '{name}': {err_body}")
        return False
    except Exception as e:
        print(f"Generic error sending Meta message for '{name}': {e}")
        return False

def send_generic_whatsapp_message(api_url, token, header_name, header_value, payload_format, recipient, product):
    """
    Sends a WhatsApp message via a generic/unofficial HTTP POST endpoint (suitable for sending to groups).
    """
    desc = product.get("description") or ""
    import re
    desc_clean = re.sub(r'<[^>]+>', '', desc)
    if len(desc_clean) > 200:
        desc_clean = desc_clean[:197] + "..."
    name = product.get("name") or "Produto"
    short_url = product.get("shortened_url") or ""
    
    body_content = f"*{name}*\n{desc_clean}\n\nConfira aqui: {short_url}"

    headers = {
        "Content-Type": "application/json"
    }
    if header_name and token:
        formatted_header_val = header_value.replace("{token}", token)
        headers[header_name] = formatted_header_val

    # Construct payload safely using JSON replacements to prevent escaping bugs
    try:
        def replace_placeholders(obj):
            if isinstance(obj, str):
                val = obj.replace("{recipient}", recipient)
                val = val.replace("{message}", body_content)
                return val
            elif isinstance(obj, dict):
                return {k: replace_placeholders(v) for k, v in obj.items()}
            elif isinstance(obj, list):
                return [replace_placeholders(item) for item in obj]
            return obj

        payload_structure = json.loads(payload_format)
        payload = replace_placeholders(payload_structure)
    except Exception as e:
        print(f"Error parsing payload template as JSON: {e}. Falling back to raw string replacement.")
        escaped_message = body_content.replace('"', '\\"').replace('\n', '\\n')
        payload_str = payload_format.replace("{recipient}", recipient).replace("{message}", escaped_message)
        try:
            payload = json.loads(payload_str)
        except Exception:
            payload = payload_str

    req_data = json.dumps(payload).encode("utf-8") if not isinstance(payload, str) else payload.encode("utf-8")

    req = urllib.request.Request(
        api_url,
        data=req_data,
        headers=headers,
        method="POST"
    )

    try:
        with urllib.request.urlopen(req) as res:
            body = res.read().decode("utf-8")
            print(f"Successfully sent generic message for '{name}' to {recipient}. API Response: {body}")
            return True
    except urllib.error.HTTPError as e:
        err_body = e.read().decode("utf-8")
        print(f"HTTP Error {e.code} sending generic message for '{name}': {err_body}")
        return False
    except Exception as e:
        print(f"Generic error sending generic message for '{name}': {e}")
        return False

def main():
    parser = argparse.ArgumentParser(description="Send Lomadee products via WhatsApp API")
    parser.add_argument("--max-messages", type=int, default=int(os.environ.get("WHATSAPP_MAX_MESSAGES", 5)),
                        help="Maximum number of messages to send in this execution")
    args = parser.parse_args()

    # Determine execution mode: Generic Custom API (e.g. for Groups) or Meta Cloud API
    api_url = os.environ.get("WHATSAPP_API_URL")
    recipient = os.environ.get("WHATSAPP_RECIPIENT_NUMBER", "556191117028")

    if api_url:
        mode = "generic"
        token = os.environ.get("WHATSAPP_API_TOKEN", os.environ.get("WHATSAPP_ACCESS_TOKEN", ""))
        header_name = os.environ.get("WHATSAPP_AUTH_HEADER_NAME", "Authorization")
        header_value = os.environ.get("WHATSAPP_AUTH_HEADER_VALUE", "Bearer {token}")
        payload_format = os.environ.get("WHATSAPP_PAYLOAD_FORMAT", '{"number": "{recipient}", "text": "{message}"}')
        
        print("--- WhatsApp Configuration (Custom API / Group Mode) ---")
        print(f"API Endpoint: {api_url}")
        print(f"Recipient / Group JID: {recipient}")
        print(f"Auth Header Name: {header_name}")
        print(f"Payload Format: {payload_format}")
        print(f"Max Messages to Send: {args.max_messages}")
        print("---------------------------------------------------------")
    else:
        mode = "meta"
        token = os.environ.get("WHATSAPP_ACCESS_TOKEN", "EAAYWHHdGTbMBRZBKUjGJ6If9wv0UXfedkwZBdMcacHCbVoSZCuOalTkDf6d5DuGVHDtPVLY6jWZCq4BQa0teZB66ldYWcuWhdcPSXYQtXPd0tczLp48Ul2wp1kaZCqhLNepc43k3NkSUgoEbrYAXqH5ZAc9XOJ6PGrdgA18uDiXOOdD4rTlddg02qE4utP0ZB3ZB4yOAUEnsYFjSnepeheUm9Eg33byZCSPZBZCZA0EebSSO508pUlhcIV0hg2PINo2cWUCDR1yYxeHVJuPPTZB5Tfy0tlZCLMAu7kHZBsy4sC9bsgZDZD")
        phone_number_id = os.environ.get("WHATSAPP_PHONE_NUMBER_ID", "1261026707084295")
        message_type = os.environ.get("WHATSAPP_MESSAGE_TYPE", "template").lower()
        template_name = os.environ.get("WHATSAPP_TEMPLATE_NAME", "hello_world")
        template_lang = os.environ.get("WHATSAPP_TEMPLATE_LANGUAGE", "en_US")
        
        print("--- WhatsApp Configuration (Meta API / Direct Mode) ---")
        print(f"Phone Number ID: {phone_number_id}")
        print(f"Recipient Number: {recipient}")
        print(f"Message Type: {message_type}")
        print(f"Template Name: {template_name}")
        print(f"Template Language: {template_lang}")
        print(f"Max Messages to Send: {args.max_messages}")
        print("---------------------------------------------------------")

    # Connect to database
    db_conn = get_postgres_connection()
    db_cur = db_conn.cursor()

    try:
        # Query products with shortened URLs
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
            if mode == "generic":
                success = send_generic_whatsapp_message(
                    api_url=api_url,
                    token=token,
                    header_name=header_name,
                    header_value=header_value,
                    payload_format=payload_format,
                    recipient=recipient,
                    product=product
                )
            else:
                success = send_meta_whatsapp_message(
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
