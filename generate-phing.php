<?php
exec("rm -rf Phing.docset/Contents/Resources/");
exec("mkdir -p Phing.docset/Contents/Resources/");
exec("wget -rkl1 http://www.phing.info/docs/guide/stable/index.html");
exec("mv " . __DIR__ . "/www.phing.info/docs/guide/stable/ " . __DIR__ . "/Phing.docset/Contents/Resources/Documents/");
exec("rm -r " . __DIR__ . "/www.phing.info");

exec("cp -r ".__DIR__."/highlightjs ".__DIR__."/Phing.docset/Contents/Resources/Documents/");
exec("cp -r ".__DIR__."/jquery ".__DIR__."/Phing.docset/Contents/Resources/Documents/");

file_put_contents(__DIR__ . "/Phing.docset/Contents/Info.plist", <<<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>CFBundleIdentifier</key>
	<string>phing</string>
	<key>CFBundleName</key>
	<string>Phing</string>
	<key>DocSetPlatformFamily</key>
	<string>phing</string>
	<key>isDashDocset</key>
	<true/>
	<key>dashIndexFilePath</key>
	<string>index.html</string>
	<key>isJavaScriptEnabled</key>
	<true/>
</dict>
</plist>
PLIST
);
copy(__DIR__ . "/icon.png", __DIR__ . "/Phing.docset/icon.png");

// highlight.js definition
$highlightjs = <<<HIGHLIGHTJS
	<link rel="stylesheet" href="highlightjs/styles/github.css">
	<script src="jquery/jquery-2.1.0.min.js"></script>
	<script src="highlightjs/highlight.pack.js"></script>
	<script>
		hljs.configure({tabReplace: '  '});
		<!--hljs.initHighlightingOnLoad();//-->
		$(document).ready(function() {
		  $('pre.programlisting').each(function(i, e) {hljs.highlightBlock(e)});
		});
	</script>
HIGHLIGHTJS;

$dom = new DomDocument;
@$dom->loadHTMLFile(__DIR__ . "/Phing.docset/Contents/Resources/Documents/index.html");

exec('rm -f ' . __DIR__ . "/Phing.docset/Contents/Resources/docSet.dsidx");
$db = new sqlite3(__DIR__ . "/Phing.docset/Contents/Resources/docSet.dsidx");
$db->query("CREATE TABLE searchIndex(id INTEGER PRIMARY KEY, name TEXT, type TEXT, path TEXT)");
$db->query("CREATE UNIQUE INDEX anchor ON searchIndex (name, type, path)");

// add links from the table of contents
$links = $edited = array();
foreach ($dom->getElementsByTagName("a") as $a) {	
	$href = $a->getAttribute("href");

	if (preg_match('/(^\w+:|^\.{1,2}|^$)/i', $href)) {
		// omit non relative links
		continue;
	}
	

	$file = preg_replace("/#.*$/", "", $href);
	if (!isset($edited[$file]) && $file != "index.html") {
		$path = __DIR__ . "/Phing.docset/Contents/Resources/Documents/" . $file;
		$html = file_get_contents($path);
		
		// Inject highlight.js
		$html = str_replace('</head', $highlightjs.'</head', $html);
		
		file_put_contents($path, $html);
		$edited[$file] = true;
	}

	$nameAndType = getNameAndType($a->nodeValue);
	if ($nameAndType) {
		$name = $nameAndType['name'];
		$type = $nameAndType['type'];
		$db->query("INSERT OR IGNORE INTO searchIndex(name, type, path) VALUES (\"$name\",\"$type\",\"$href\")");
	}
}

function getNameAndType($string) {
	if(preg_match('/(^[A-Z]|\d+)\.(?:\d+\.)?\ (.*?)$/', $string, $matches)) {
		$rawType = trim($matches[1]);
		$name = trim($matches[2]);
	
		switch ($rawType) {
			case '5':
				$type = 'Component';
				break;
			case 'B':
			case 'C':
				$type = 'Method';
				break;
			case 'D':
				$type = 'Type';
				break;
			case 'E':
				$type = 'Filter';
				break;
			case 'F':
				$type = 'Filter';
				if (preg_match('/Attributes$/', $name)) {
					$type = 'Attribute';
				}
				break;
			case 'G':
				$type = 'Operator';
				break;
			case 'H':
				$type = 'Components';
				break;
			case 'I':
				$type = 'File';
				break;
			default:
				$type = 'Guide';
				{
					if (preg_match('/Properties$/', $name)) {
						$type = 'Property';
					}
					elseif (preg_match('/Arguments$/', $name)) {
						$type = 'Property';
					}
					elseif (preg_match('/File Layout$/', $name)) {
						$type = 'File';
					}
					elseif (preg_match('/Codes$/', $name)) {
						$type = 'Enum';
					}
				}
				break;
		}

		return ['name' => $name, 'type' => $type];
	} else {
		return false;
	}
}