<?php
/*
	Combine and minify CSS

	Mark Roland

	Sample Calls:

		php process-css.php -v 007 -u
		php process-css.php -v 007
		php process-css.php -v 007 -m -p

	12/30/2012 - Prepared for publication
*/


/*
	Configure script settings
*/


/*
	Get command line interface options:

	-c ... Combine files
	-v ... Set version for output file
	-m ... Minify
	-u ... Check URLs
	-p ... Publish
*/
$opts = getopt('v:cmupi');

// Set file path to Yahoo UI Compressor 
$yui_compressor_path = 'yuicompressor-2.4.2.jar';

// Set filename of index file that contains @import directives
$input_file = 'index.css';

// Set input directory that contains $input_file
$input_directory = 'sample-css/input/';

// Set output directory
$output_file = 'sample-css/output/style_X.css';


/*
	Set flags based on command line input
*/


// "c" option - Set Minify
if( isset($opts['m']) )
	$combine = true;

// "v" option - Set version
// This replaces "_X" with the specified version. For example style_X.css becomes style_5.css
// if the "v" option is set to 5
if( isset($opts['v']) )
	$output_file = str_replace('_X', '_'.$opts['v'], $output_file);

// "m" option - Set Minification option
if( isset($opts['m']) ){
	$minify = true;
	$mini_file_output = str_replace('.css', '_min.css', $output_file);
}

// "u" option - Set Check URLs
if( isset($opts['u']) )
	$check_urls = true;

// "p" option - Publish to remote server
if( isset($opts['p']) ){
	$upload = true;
	$remote_username = 'foo';
	$remote_domain = 'bar.com';
}


/*
	Begin processing files
*/


// Open source file that contains @import css directives
$lines = file($file);

// Initialize CSS
$css = '';

// Combine the CSS files
if( $combine ){

	// Format Destination
	$mini_file_input = $output_file;

	// Read in contents of imports
	foreach( (array)$lines as $line){

		// Find all "@import" directives, open those files and save them to a new string
		if( preg_match('/^@import url\("?\'?([a-zA-Z0-9_\.\-]*)"?\'?\);/', $line, $matches) ){

			// Append file contents to CSS string
			$css .= file_get_contents($input_directory.$matches[1]);

			// If the "check URLs" option is selected, make requests for all absolute URLs found
			// in the CSS. 
			// TODO: Update this to use PHP instead of shell command
			// TODO: Update this to recognize relative URLs
			if( $check_urls ){
				$command = sprintf("for var in `grep -o -e \"http.*'\" %s | sed \"s/\'//g\"`; do echo \$var; curl --head --silent \$var | grep HTTP; done", $input_directory.$matches[1]);
				//echo 'Executing: '.$command."\n";
				//$command = escapeshellcmd($command);
				$output = shell_exec($command);
				print($output);
			}

		}

	} // End foreach

	// Use protocol-free URLs (Change "http://" and "https://" to simply "//")
	// This eliminates "insecure content" warnings from browsers that do not like resources like 
	// images from being served using http when the main page is requested over https
	$css = str_replace('http://', "//", $css);
	$css = str_replace('https://', "//", $css);

	// Save CSS to file
	file_put_contents($output_file, $css);

}else{

	// If combination not requested, simply rename the file
	// TODO: test this
	$output_file = $file;
}

// Minify CSS
if( $minify ){

	// Using Yahoo UI compressor to perform minification
	$command = sprintf(
		'java -jar %s --type css -o %s %s',
		$yui_compressor_path,
		$mini_file_output,
		$output_file
	);
	exec($command);

	// Add line breaks to minified CSS
	$minified_lines = file($mini_file_output);
	$updated_mini = str_replace('}', "}\n", $minified_lines);

	// Fix media queries
	$updated_mini = str_replace('and(', "and (", $updated_mini);
	$updated_mini = preg_replace('/(@media[^\{]*\{)(.*)/', "\$1\n$2", $updated_mini);

	// Save minification to output file
	file_put_contents($mini_file_output, $updated_mini);
}

// Upload output file to remote server
if( $upload ){
	$scp_command = sprintf("scp %s %s@%s:%s", $mini_file_output, $remote_username, $remote_domain, $mini_file_output);
	printf("Sending %s to server...\n", $mini_file_output);
	exec($scp_command);
}

// Display completion message
print('Finished');

?>