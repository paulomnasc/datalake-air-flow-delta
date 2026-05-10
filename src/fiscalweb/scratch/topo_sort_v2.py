import re

with open('/root/datalake-air-flow-delta/src/fiscalweb/app/Database/script_ddl/ddl-v2.sql', 'r') as f:
    content = f.read()

# Extract all create table blocks
blocks = re.findall(r'CREATE TABLE (.*?);', content, re.DOTALL | re.IGNORECASE)

tables = {}
for block in blocks:
    # Get table name
    match = re.search(r'^(\w+)\s*\(', block.strip())
    if match:
        name = match.group(1).lower()
        tables[name] = 'CREATE TABLE ' + block.strip() + ';'

# Find dependencies
deps = {name: set() for name in tables}
for name, block in tables.items():
    fks = re.findall(r'FOREIGN KEY.*?REFERENCES\s*(\w+)', block, re.IGNORECASE)
    for fk in fks:
        fk_name = fk.lower()
        if fk_name in tables and fk_name != name:
            deps[name].add(fk_name)

# Topological sort
sorted_tables = []
visited = set()

def visit(node):
    if node in visited: return
    for dep in deps[node]:
        visit(dep)
    visited.add(node)
    sorted_tables.append(node)

for name in tables:
    visit(name)

sorted_ddl = '\n\n'.join(tables[name] for name in sorted_tables)

# Now read ddl.sql up to line 35
with open('/root/datalake-air-flow-delta/src/fiscalweb/app/Database/script_ddl/ddl.sql', 'r') as f:
    orig_ddl = f.read()
lines = orig_ddl.split('\n')
base_ddl = '\n'.join(lines[:35])

final_ddl = base_ddl + '\n\n' + sorted_ddl

with open('/root/datalake-air-flow-delta/src/fiscalweb/app/Database/script_ddl/ddl.sql', 'w') as f:
    f.write(final_ddl)
print("Sorted and updated ddl.sql")
