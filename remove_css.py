import re
filename = r'c:\xampp\htdocs\V6.7.5\IRIS\admin\includes\header.php'
with open(filename, 'r', encoding='utf-8') as f:
    content = f.read()

new_content = re.sub(r'\s*html\.dark #studioNotesInput,', '', content)

with open(filename, 'w', encoding='utf-8') as f:
    f.write(new_content)
print("Updated header.php")
