import re

with open('table.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Find the last query
idx = content.rfind('withdrawal_requests')
if idx != -1:
    end_of_block = content.find(')', idx) + 1
    new_table = '''
        \ = \->query(\"CREATE TABLE IF NOT EXISTS admin_payment_messages (
          id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          id_order varchar(200) NOT NULL,
          admin_id varchar(200) NOT NULL,
          message_id varchar(200) NOT NULL)\");
'''
    new_content = content[:end_of_block] + new_table + content[end_of_block:]
    with open('table.php', 'w', encoding='utf-8') as f:
        f.write(new_content)
    print("Updated table.php")
else:
    print("Could not find insertion point")
