<pre><?php
$url = 'http://api.wordpress.org/core/version-check/1.6/?version=3.2.1&php=5.3.8&locale=en_US&mysql=5.1.58&local_package=&blogs=1&users=1&multisite_enabled=0';

$args = array(
	"method" => "GET",
	"timeout" => 2, // Default was 3
	"redirection" => 5,
	"httpversion" => "1.0",
	"user-agent" => "WordPress/3.2.1; http://sephp.dev/wordpress/",
	"blocking" => true,
	"headers" => array(
		"wp_install" => "http://sephp.dev/wordpress/",
		"wp_blog" => "http://sephp.dev/wordpress/",
		"Accept-Encoding" => "deflate;q=1.0, compress;q=0.5",
	),
	"cookies" => array(),
	"body" => NULL,
	"compress" => false,
	"decompress" => true,
	"sslverify" => true,
	"stream" => false,
	"filename" => NULL,
	"_redirection" => 5,
	"ssl" => false,
	"local" => false,
);

request( $url, $args );

function request($url, $args = array()) {
	$defaults = array(
		'method' => 'GET', 'timeout' => 5,
		'redirection' => 5, 'httpversion' => '1.0',
		'blocking' => true,
		'headers' => array(), 'body' => null, 'cookies' => array()
	);

	$r = wp_parse_args( $args, $defaults );

	if ( isset($r['headers']['User-Agent']) ) {
		$r['user-agent'] = $r['headers']['User-Agent'];
		unset($r['headers']['User-Agent']);
	} else if ( isset($r['headers']['user-agent']) ) {
		$r['user-agent'] = $r['headers']['user-agent'];
		unset($r['headers']['user-agent']);
	}

	buildCookieHeader( $r );

	$handle = curl_init();
	$is_local = isset($args['local']) && $args['local'];
	$ssl_verify = isset($args['sslverify']) && $args['sslverify'];


	// CURLOPT_TIMEOUT and CURLOPT_CONNECTTIMEOUT expect integers.  Have to use ceil since
	// a value of 0 will allow an ulimited timeout.
	$timeout = (int) ceil( $r['timeout'] );
	curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, $timeout ); dump_opt( "CURLOPT_CONNECTTIMEOUT", $timeout );
	curl_setopt( $handle, CURLOPT_TIMEOUT, $timeout ); dump_opt( "CURLOPT_TIMEOUT", $timeout );

	curl_setopt( $handle, CURLOPT_URL, $url);
	curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true ); dump_opt( "CURLOPT_RETURNTRANSFER", true );
	curl_setopt( $handle, CURLOPT_SSL_VERIFYHOST, ( $ssl_verify === true ) ? 2 : false );
	dump_opt( "CURLOPT_SSL_VERIFYHOST", ( $ssl_verify === true ) ? 2 : false);
	curl_setopt( $handle, CURLOPT_SSL_VERIFYPEER, $ssl_verify ); dump_opt( "CURLOPT_SSL_VERIFYPEER", $ssl_verify );
	curl_setopt( $handle, CURLOPT_USERAGENT, $r['user-agent'] ); dump_opt( "CURLOPT_USERAGENT", $r['user-agent'] );
	curl_setopt( $handle, CURLOPT_MAXREDIRS, $r['redirection'] ); dump_opt( "CURLOPT_MAXREDIRS", $r['redirection'] );

	switch ( $r['method'] ) {
		case 'HEAD':
			curl_setopt( $handle, CURLOPT_NOBODY, true ); dump_opt( "CURLOPT_NOBODY", true );
			break;
		case 'POST':
			curl_setopt( $handle, CURLOPT_POST, true ); dump_opt( "CURLOPT_POST", true );
			curl_setopt( $handle, CURLOPT_POSTFIELDS, $r['body'] ); dump_opt( "CURLOPT_POSTFIELDS", $r['body']  );
			break;
		case 'PUT':
			curl_setopt( $handle, CURLOPT_CUSTOMREQUEST, 'PUT' ); dump_opt( "CURLOPT_CUSTOMREQUEST", 'PUT' );
			curl_setopt( $handle, CURLOPT_POSTFIELDS, $r['body'] ); dump_opt( "CURLOPT_POSTFIELDS", $r['body'] );
			break;
	}

	if ( true === $r['blocking'] )
	{
		curl_setopt( $handle, CURLOPT_HEADERFUNCTION, 'header_callback' ); 
		dump_opt( "CURLOPT_HEADERFUNCTION", "header_callback" );
	}

	curl_setopt( $handle, CURLOPT_HEADER, false ); dump_opt( "CURLOPT_HEADER", false );

	// The option doesn't work with safe mode or when open_basedir is set.
	if ( !ini_get('safe_mode') && !ini_get('open_basedir') && 0 !== $r['_redirection'] )
	{
		curl_setopt( $handle, CURLOPT_FOLLOWLOCATION, true ); dump_opt( "CURLOPT_FOLLOWLOCATION", true );
	}

	if ( !empty( $r['headers'] ) ) {
		// cURL expects full header strings in each element
		$headers = array();
		foreach ( $r['headers'] as $name => $value ) {
			$headers[] = "{$name}: $value";
		}
		curl_setopt( $handle, CURLOPT_HTTPHEADER, $headers ); dump_opt( "CURLOPT_HTTPHEADER", $headers );
	}

	if ( $r['httpversion'] == '1.0' )
	{
		curl_setopt( $handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0 ); 
		dump_opt( "CURLOPT_HTTP_VERSION", CURL_HTTP_VERSION_1_0 );
	}
	else
	{
		curl_setopt( $handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
		dump_opt( "CURLOPT_HTTP_VERSION", CURL_HTTP_VERSION_1_1 );
	}

	// We don't need to return the body, so don't. Just execute request and return.
	if ( ! $r['blocking'] ) {
		die( '<div>142</div>' ); //@DEBUG
		curl_exec( $handle );
		curl_close( $handle );
		return array( 'headers' => array(), 'body' => '', 'response' => array('code' => false, 'message' => false), 'cookies' => array() );
	}
	$theResponse = curl_exec( $handle );
	$theBody = '';
	die('IT WORKS!');
}
function wp_parse_args( $args, $defaults = '' ) {
	if ( is_object( $args ) )
		$r = get_object_vars( $args );
	elseif ( is_array( $args ) )
		$r =& $args;
	else
		wp_parse_str( $args, $r );

	if ( is_array( $defaults ) )
		return array_merge( $defaults, $r );
	return $r;
}
// Construct Cookie: header if any cookies are set.
function buildCookieHeader( &$r ) {
	if ( ! empty($r['cookies']) ) {
		$cookies_header = '';
		foreach ( (array) $r['cookies'] as $cookie ) {
			$cookies_header .= $cookie->getHeaderValue() . '; ';
		}
		$cookies_header = substr( $cookies_header, 0, -2 );
		$r['headers']['cookie'] = $cookies_header;
	}
}

function header_callback( $r, $str)
{
	echo "Callback functon\n";
}

function dump_opt( $name, $value)
{
	if (is_array($value))
		echo "$name => ". print_r( $value, true ) . "\n";
	else
		echo "$name => $value\n";
}
?></pre>
