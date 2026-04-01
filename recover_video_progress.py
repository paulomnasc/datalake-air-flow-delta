import os
import glob
import re

LOG_DIR = "src/codeigniter-app/writable/logs/"
OUTPUT_FILE = "/tmp/recovered_video_progress.sql"

queries = set()

with open(OUTPUT_FILE, "w", encoding="utf-8") as out_f:
    out_f.write("USE `lista_revisao2`;\n\n")
    
    for log_file in sorted(glob.glob(os.path.join(LOG_DIR, "log-*.log"))):
        with open(log_file, "r", encoding="utf-8", errors="replace") as in_f:
            for line in in_f:
                if "INSERT INTO `video_progress`" in line and "VALUES (:" not in line:
                    # Find the start of the query
                    start_idx = line.find("INSERT INTO `video_progress`")
                    if start_idx != -1:
                        # Extract everything from the start to the end of the line, then find the correct ending parenthesis.
                        substr = line[start_idx:]
                        # The query ends where the values parenthesis closes. Usually something like:
                        # VALUES (\'123\', ... \'date\')') or VALUES ('123', ... 'date')', 0)
                        
                        # We will simply parse out the exact string avoiding regex greediness:
                        # Find the last `)` before the `, 0)` or outside quotes.
                        # It's easier: The query normally ends with `)`.
                        # Let's match from `INSERT INTO` up to `)` that precedes `',` or `')`
                        
                        match = re.search(r"(INSERT INTO `video_progress`.+?VALUES\s*\([^)]+\))", substr)
                        if match:
                            raw_query = match.group(1)
                            # Handle backslash-escaped quotes from CodeIgniter's log
                            raw_query = raw_query.replace("\\'", "'")
                            
                            if raw_query not in queries:
                                queries.add(raw_query)
                                upsert_clause = " ON DUPLICATE KEY UPDATE percent = GREATEST(percent, VALUES(percent)), watched_seconds = GREATEST(watched_seconds, VALUES(watched_seconds)), completed = GREATEST(completed, VALUES(completed)), last_position_seconds = GREATEST(last_position_seconds, VALUES(last_position_seconds)), updated_at = VALUES(updated_at);"
                                out_f.write(raw_query + upsert_clause + "\n")

print(f"Extraction complete! Found {len(queries)} unique queries.")
print(f"Saved to {OUTPUT_FILE}")
