<?php

die('disabled');

$masks = array(
	'ActiveRecord.php',
	'examples/orders/*.php',
	'examples/orders/models/*.php',
	'examples/simple/*.php',
	'lib/*.php',
	'lib/adapters/*.php',
	'lib/cache/*.php',
	'test/*.php',
	'test/helpers/*.php',
	'test/models/*.php',
	'test/models/NamespaceTest/*.php',
);

$files = array();

foreach($masks as $mask)
{
	$newFiles = glob($mask);
	$files = array_merge($files, $newFiles);
}

$pattern = '_*[a-z0-9]+_[a-z0-9_]*';

$patterns = array(
	'\$' . $pattern,
	'->' . $pattern,
	'::\$?' . $pattern,
	'function\s+&?' . $pattern . '\s*\(',
);

$exceptions = array(
	'function array_flatten(',
	'function get_namespaces(',
	'function has_namespace(',
	'function is_hash(',
	'function wrap_strings_in_arrays(',
);

foreach($files as $file)
{
	echo $file, '<br />';
	
	$data = file_get_contents($file);
	
	$data = preg_replace_callback('/(?:' . implode('|', $patterns) . ')/i', function($s) use($exceptions) {
		$s = $s[0];
		
		if(in_array($s, $exceptions))
			return $s;
		
		if(preg_replace('/[^a-z]/', '', $s)) // No caps lock.
			return camelize($s);
		else
			return $s;
	}, $data);
	
	file_put_contents($file, $data);
}

echo 'done';

function camelize($s)
{
	if(!preg_match('/^((?:->|::|function\s+&?)?\$?_*)(.*?)(\s*\(?)$/', $s, $match))
	{
		echo 'Failed to match `', $s, '`';
		die;
	}
	
	$res = $match[1];
	$s = $match[2];
	preg_replace('/_+/', '_', $s);
	
	for($i = 0, $n = strlen($s); $i < $n; $i++)
	{
		if($s[$i] == '_' && $i < $n - 1)
			$s[$i + 1] = strtoupper($s[$i + 1]);
		else
			$res .= $s[$i]; // Note: trailing _ will be attached, if any.
	}
	
	$res .= $match[3];
	
	return $res;
}
