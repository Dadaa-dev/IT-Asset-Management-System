import re

with open('config.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Find the echo block with DOCTYPE and replace with PWA-enhanced version
old_head = "| AssetVault</title>\n    <link rel=\\\"stylesheet\\\" href=\\\"style.css\\\"></head>"
new_head = ("| AssetVault SPIST</title>\n"
            "    <meta name=\\\"theme-color\\\" content=\\\"#d4af37\\\">\n"
            "    <meta name=\\\"mobile-web-app-capable\\\" content=\\\"yes\\\">\n"
            "    <meta name=\\\"apple-mobile-web-app-capable\\\" content=\\\"yes\\\">\n"
            "    <meta name=\\\"apple-mobile-web-app-status-bar-style\\\" content=\\\"black-translucent\\\">\n"
            "    <link rel=\\\"manifest\\\" href=\\\"manifest.json\\\">\n"
            "    <link rel=\\\"apple-touch-icon\\\" href=\\\"assets/icons/icon-192.png\\\">\n"
            "    <link rel=\\\"stylesheet\\\" href=\\\"style.css\\\"></head>")

if old_head in content:
    content = content.replace(old_head, new_head, 1)
    with open('config.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print('SUCCESS: PWA meta tags added to config.php')
else:
    print('NOT FOUND - searching for actual head section...')
    idx = content.find('AssetVault</title>')
    print(repr(content[max(0,idx-50):idx+80]))
