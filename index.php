<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require 'vendor/autoload.php';

date_default_timezone_set('UTC');

spl_autoload_register(function ($classname) {
	require ('classes/' . $classname . '.php');
});

$app = new \Slim\App;
$container = $app->getContainer();

$container['logger'] = function($c) {
	$logger = new \Monolog\Logger('my_logger');
	$file_handler = new \Monolog\Handler\StreamHandler('logs/moers.log');
	$logger->pushHandler($file_handler);
	return $logger;
};

function getWait() {
	function getCurrentWaitDataXML()
	{
		$dataURL = 'https://www.moers.de/de/opendataxml/wartezeiten/';
		return file_get_contents( $dataURL);
	}

	function convertWaitTicketJSON( $xmlContent)
	{
		if( '' == $xmlContent) {
			return array('waitingtime' => 0, 'ticketnumber' => 0, 'numberofpeople' => 0);
		}

		$xml = simplexml_load_string( $xmlContent);
		$json = json_encode( $xml);
		$array = json_decode( $json, TRUE);

		if( !isset($array['eintrag'])) {
			return array('waitingtime' => 0, 'ticketnumber' => 0, 'numberofpeople' => 0);
		}

		foreach( $array['eintrag'] as $value) {
			$people = intval( $value['personenzahl']);
			$number = intval( $value['ticketnummer']);
			$wait = intval( $value['wartezeit']);
			$timestamp = $value['zeitstempel'];
			$h = intval( substr( $timestamp, strpos( $timestamp, ' ') + 1, 2));

			$day = intval( substr( $timestamp, 0, 2));
			$month = intval( substr( $timestamp, strpos( $timestamp, '.') + 1, 2));
			$year = intval( substr( $timestamp, strpos( $timestamp, ' ') - 4, 4));
			$datetime = mktime( $h, 0, 0, $month, $day, $year);
			$diffMin = (mktime() - $datetime) / 60;

			if( 0 == $year) {
				return array('waitingtime' => 2, 'ticketnumber' => 1, 'numberofpeople' => 1);
			}

			if( $diffMin < 100) {
				if( $number > 0) {
					return array('waitingtime' => $wait, 'ticketnumber' => $number, 'numberofpeople' => $people);
				}
			}
		}

		return array('waitingtime' => 0, 'ticketnumber' => 0, 'numberofpeople' => 0);
	}

	return convertWaitTicketJSON(getCurrentWaitDataXML());
}

$app->get('/api/moers/v1/wait/current', function ($request, $response, $args) {
	$this->logger->addInfo('wait/current');
	$params = $request->getQueryParams();

	$code = 200;
	$data = getWait();

	$response = $response->withJson($data, $code);
	return $response;
});

$app->run();
?>
