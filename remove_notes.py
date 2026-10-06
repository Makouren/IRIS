import re
import sys

def remove_notes(filename):
    with open(filename, 'r', encoding='utf-8') as f:
        content = f.read()

    # Regex to match the entire div for Admin Verification Notes
    pattern = re.compile(r'\s*<div style="grid-column: 1 / -1;">\s*<label class="form-label"[^>]*>Admin Verification Notes</label>\s*<textarea id="studioNotesInput"[^>]*></textarea>\s*</div>')
    
    new_content = pattern.sub('', content)
    
    with open(filename, 'w', encoding='utf-8') as f:
        f.write(new_content)
    print(f"Updated {filename}")

remove_notes(r'c:\xampp\htdocs\V6.7.5\IRIS\admin\review_editor.php')
remove_notes(r'c:\xampp\htdocs\V6.7.5\IRIS\scanner\index.php')
