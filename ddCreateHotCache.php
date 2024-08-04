//<?php
/**
 * ddCreateHotCache
 * @version 0.2.1 (2018-07-09)
 * 
 * @desc Плагин для создания «Горячего» кэша.
 * 
 * @uses PHP >= 5.6
 * @uses (MODX)EvolutionCMS.snippets.ddGetDocuments >= 0.9
 * @uses (MODX)EvolutionCMS.libraries.ddTools >= 0.40.1
 * 
 * @param $params {arrayAssociative|stdClass} — Параметры конфигурации плагина.
 * @param $params['parentIds'] {integer|string_commaSeparated} — Id документа с которого начнется сканирование. Default: 0.
 * @param $params['depth'] {integer} — Глубина просмотра. Default: 2.
 * @param $params['orderBy'] {string} — SQL order by. Разделитель - #. Default: '#menuindex# DESC'.
 * @param $params['filter'] {string} — SQL where. Разделитель - #. Default: '#published# = 1 AND #hidemenu# = 0 AND #deleted# = 0'.
 * @param $params['timeout'] {integer} — Максимально позволенное количество секунд на соедение со страницей. Default: 30.
 * 
 * @event OnSiteRefresh
 * 
 * @copyright 2018 DD Group {@link https://DivanDesign.biz }
 **/

$e = &$modx->event;

if($e->name == 'OnSiteRefresh'){
	// 
	@ignore_user_abort(true);
	// 
	@set_time_limit(0);
	// 
	@ini_set(
		'memory_limit',
		'1G'
	);
	
	// Prepare params
	$params = \DDTools\ObjectTools::extend([
		'objects' => [
			// Defaults
			(object) [
				'parentIds' => 0,
				'depth' => 2,
				'orderBy' => '#menuindex# DESC',
				'filter' => '
					#published# = 1 AND
					#hidemenu# = 0 AND
					#deleted# = 0
				',
				'timeout' => 30
			],
			$params
		]
	]);
	
	// Get required docs
	$result = $modx->runSnippet(
		'ddGetDocuments',
		[
			'provider' => 'parent',
			'providerParams' => '{
				"parentIds": "'. $params->parentIds .'",
				"depth": "'. $params->depth .'"
			}',
			'fieldDelimiter' => '#',
			'filter' => $params->filter,
			'orderBy' => $params->orderBy,
			'outputter' => 'json',
			'outputFormatParams' => '{
				"docFields": "id"
			}'
		]
	);
	
	$result = json_decode(
		$result,
		true
	);
	$fatal = [];
	
	foreach(
		$result as
		$doc
	){
		// Send request
		request(
			$modx->makeUrl(
				$doc['id'],
				'',
				'',
				'full'
			),
			$params->timeout,
			$fatal
		);
	}
	
	if(count($fatal)){
		$modx->logEvent(
			1,
			1,
			(
				'<code><pre>' .
				print_r(
					$fatal,
					true
				) .
				'</pre></code>'
			),
			'ddCreateHotCache: Error list'
		);
	}
}

function request(
	$url,
	$timeout,
	&$result = [],
	$count = 0
){
	$ch = curl_init();
	
	// URL
	curl_setopt(
		$ch,
		CURLOPT_URL,
		$url
	);
	// 
	curl_setopt(
		$ch,
		CURLOPT_RETURNTRANSFER,
		1
	);
	// Timeout
	curl_setopt(
		$ch,
		CURLOPT_TIMEOUT,
		$timeout
	);
	
	// Go
	curl_exec($ch);
	// Get response code (we don't need anything else)
	$httpCode = curl_getinfo(
		$ch,
		CURLINFO_HTTP_CODE
	);
	// Done
	curl_close($ch);
	
	// If fail
	if($httpCode != 200){
		// We'll try 3 times
		if($count < 3){
			$count++;
			
			// TODO: Может быть здесь взять паузу небольшую перед следующей попыткой?
			request(
				$url,
				$timeout,
				$result,
				$count
			);
		// Каунтер перевалил 
		}else{
			$result[$httpCode][] = $url;
		}
	}
}
