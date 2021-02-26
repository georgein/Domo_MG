<?php

/************************************************************************************************************************
* 										SERIE D'UTILITAIRES POUR LA MAINTENANCE DE ZWave								*
*************************************************************************************************************************
*																														*
* TOUS LES DETAILS SUR file:///C:/Users/Michel/Google%20Drive/Doc/Jeedom/API%20RESTful%20du%20plugin%20ZWave.html		*
*																														*
************************************************************************************************************************/

$logTimeLine = mg::getParam('Log', 'timeLine');
$apizwave = mg::getConfigJeedom('openzwave');


// Détecteur des nœuds sur piles qui ne répondent plus
//NodesMissedWakeUp($apizwave, $logTimeLine);

// Demander une mise à jour forcée de du niveau des piles si pas mis à jour depuis $dayOld
$dayOld = 30;
//BatteriesRefresh($apizwave, $dayOld);

// Essayer de réanimer les nœuds présumés morts
//ReviveDeadNodes($apizwave);

// MàJ Aléatoire des noeuds voisins et RefreshAllValue
$type = 'battery, sector';
$action = 'requestNodeNeighbourUpdate';
$action = 'refreshAllValues';
$timer = 3600;
//ActionListe($apizwave, $type, $action, $timer);

// Mise à jour des nœuds voisins (A lancer deux fois par semaines si réseau pas stabilisé)
HealNetwork($apizwave);


/************************************************************************************************************************
* 														WAIT BUSY														*
*************************************************************************************************************************
* Attend que le server ne soit plus 'busy' via un ping sur le Node N° 1													*
************************************************************************************************************************/
function WaitBusy($timer=5) {
	$networkState = openzwave::callOpenzwave('/network?type=info&info=getStatus');
	$queueSize = $networkState['result']['outgoingSendQueue'];
	while ($queueSize > 0) {
		$networkState = openzwave::callOpenzwave('/network?type=info&info=getStatus');
		$queueSize = $networkState['result']['outgoingSendQueue'];
		mg::message('', "Attente Queue sortante à 0 : $queueSize");
		if ($timer > 0) { sleep($timer); }
		mg::message('', print_r($networkState, true));
	}
	mg::message('', "Zwave DISPO !!!");
	return 'ok';
}

/************************************************************************************************************************
* 														ACTION LISTE													*
*************************************************************************************************************************
* Traite la liste des noeud de $type avec $action de manière aléatoire pendant $time sec maximum						*
*																														*
*	Paramètres																											*
*	apizwave 	: API de ZWave																							*
*	type		: Type d'équipement à lister (battery, sector, dead) 													*
*	actions		: Liste des action Zwave à effectuer séparé par une ','													*
*	timer		: Durée maximum du traitement avant de sortir															*
*																														*
************************************************************************************************************************/
function ActionListe($apizwave, $type, $actions, $timer) {
	$wait = WaitBusy(); // Attente de dispo de Zwave
	// Routine de choix aléatoire dans le tableau des noeuds
	$randomChoice  = function($array) {return $array[array_rand($array)];};

	$nodeIds = NodesList($apizwave, $type);
	$timeDeb = time();
	$cpt = 0;
	mg::messageT('', "!Liste des " . count($nodeIds) . " noeuds ($type) à traiter aléatoirement avec ($actions) : \n" . implode(',', $nodeIds));
	while($timeDeb + $timer > time()) {
		$nodeId = $randomChoice($nodeIds);
		$tabActions = explode(',', $actions);
		foreach($tabActions as $action) {
			$cpt++;
			$url = "http://127.0.0.1:8083/node?node_id=$nodeId&type=action&action=$action&apikey=$apizwave";
			mg::messageT('', ". Action ($cpt) $action noeud $nodeId");
			$contents = file_get_contents($url);
			$results = json_decode($contents);
			$success = $results->state;
			$wait = WaitBusy(10); // Attente de dispo de Zwave
			mg::message('', "Action ($cpt) $action noeud $nodeId return $success");
		}
	}
	mg::messageT('', ". $cpt noeuds ($type) de traités  avec ($actions) : \n" . implode(',', $nodeIds)); 
	$url = 'http://localhost:8083/network?type=info&info=manualBackup&apikey=' . $apizwave;
}

/************************************************************************************************************************
* 														NODELIST														*
*************************************************************************************************************************
* Retourne un tableau de tous les noeuds des équipements de type battery/sector/dead									*
*																														*
*	Paramètres																											*
*	apizwave 	: API de ZWave																							*
*	type 		: battery / sector / dead : On ne retourne QUE les équipements participant à TOUS les types donnés		*
*																														*
************************************************************************************************************************/
function NodesList($apizwave, $type) {
	global $scenario;
	$url_health = 'http://localhost:8083/network?type=info&info=getHealth&apikey=' . $apizwave;
	$content = (file_get_contents($url_health));
	$results = json_decode($content, true);
	$success = $results["state"];
		if ($success != 'ok') {
		$scenario->setLog('ZAPI network getHealth return aune erreur: ' . $results["result"]);
	} else {
		// get the full node list
		$devices = $results["result"]["devices"];
		$node_List = array();
		foreach ($devices as $nodeId => $node_values) {
			$node_name = $node_values["data"]["description"]["name"];

			$isEnabled = $node_values["data"]["is_enable"]["value"]; // Si actif
			$isFailed = $node_values["data"]["isFailed"]["value"]; // Si Dead
			$isListening = $node_values["data"]["isListening"]["value"]; // Si sur secteur
			if ($isEnabled) {
				if (!$isFailed && strpos($type, 'dead') !== false) { continue; }
				if (!$isListening && strpos($type, 'battery') === false || $isListening && strpos($type, 'sector') === false) { continue; }
			}
			$node_List[] = $nodeId;
		}
	}
	return $node_List;
}

/************************************************************************************************************************
* 														NODEACTION														*
*************************************************************************************************************************
* Lance une action sur un neoud et attend la fin pour sortir															*
*																														*
*	Paramètres																											*
*	apizwave 	: API de ZWave																							*
*	action		: Action Zwave à effzctuer :																			*
*							'testNode'						Exécuter un test (ping)										*
							'refreshAllValues'				Demande de rafraîchir toutes les valeurs du nœud			*
							'requestNodeNeighbourUpdate'	Demande de mise à jour des voisins du noeud					*
							'healNode' 						Demande à soigner le noeud									*
							'hasNodeFailed'					Demande si le nœud est présumé mort par le contrôleur		*
							'requestNodeDynamic'			Demande de refaire l’étape de l’interview Dynamic			*
							'assignReturnRoute'				Forcer une route de retour au contrôleur					*
*																														*
************************************************************************************************************************/
function NodeAction($apizwave, $action, $nodeId) {
	global $scenario;
	$wait = WaitBusy(); // Attente de dispo de Zwave
	$url = "http://127.0.0.1:8083/node?node_id=$nodeId&type=action&action=$action&apikey=$apizwave";
	mg::messageT('', ". Action $action noeud $nodeId");
	deb:
	$wait = WaitBusy(); // Attente de dispo de Zwave
	$contents = file_get_contents($url);
	$results = json_decode($contents);
	$success = $results->state;
	mg::message('', "Action $action noeud $nodeId return $success");
	$wait = WaitBusy(); // Attente de dispo de Zwave
}


/************************************************************************************************************************
* 													ReviveDeadNodes														*
*************************************************************************************************************************
* Essayer de réanimer un nœud																							*
* Il peut arriver pour diverses raisons qu’un nœud devienne présumé mort par le contrôleur. Dans de tel cas le 			*
* contrôleur n’envoie plus de commandes à ce module.																	*
* On peut se retrouver avec le chauffage bloqué en marche ou à l’arrêt avec ce genre d’expérience.						*
*																														*
* Il est en général possible de réanimer la majorité des modules présumés mort. Une action ping sur un nœud présumé mort*
* arrive en général à corriger la situation.																			*
*																														*
* Essayer de réanimer les nœuds présumés morts.					 														*
*																														*
************************************************************************************************************************/
function ReviveDeadNodes($apizwave) {
//	$nodeIds = array(77, 23, 100);
	$wait = WaitBusy(); // Attente de dispo de Zwave
	$nodeIds = NodesList($apizwave, 'dead');

	mg::messageT('', "!Liste des " . count($nodeIds) . " noeuds présumés 'dead' à réactiver : " . implode(',', $nodeIds));

	foreach ($nodeIds as $nodeId) {
		// Si le noeud est bien considéré comme 'dead'
		if (getNodeFailed($apizwave, $nodeId)) {
			mg::message('', "Faire un ping sur node $nodeId pour tenter de le réveiller");
			$results = NodeAction($apizwave, 'testNode', $nodeId);
			$success = $results->state;
			if ($success == 'ok') {
		if (getNodeFailed($apizwave, $nodeId)) {
					mg::message('', "Faire un hasNodeFailed sur node $nodeId pour REtenter de le réveiller");
					$results = NodeAction($apizwave, 'hasNodeFailed', $nodeId);
					$success = $results->state;
					if ($success == 'ok') {
						// Si le noeud est toujours considéré comme 'dead'
						sleep(5);
						getNodeFailed($apizwave, $nodeId);
					}
				}
			}
		}
	}
}

	function getNodeFailed($apizwave, $nodeId){
		$url = "http://localhost:8083/node?node_id=$nodeId&type=info&info=getHealth&apikey=$apizwave";
		$content = file_get_contents($url);
		$results = json_decode($content, true);
		$success = $results["state"];
		if ($success != 'ok') {
			mg::message('', 'getHealth return une erreur : ' . $results["result"]);
			//Je ne peux confirmer quoi que ce soit, nous supposons que c'est un échec.
			return false;
		} else {
			if ($results["result"]["data"]["isFailed"]["value"]) {
				mg::messageT('', ". ****** nodeid $nodeId is failed ******");
			}
			return true;
		}
	}

/************************************************************************************************************************
* 													NodesMissedWakeUp													*
*************************************************************************************************************************
* Détecteur des nœuds sur piles qui ne répondent plus																	*
* On souhaite détecter si nos modules sur piles sont toujours actifs sur le réseau. Un module sur pile dort et ne passe *
* pas en présumé mort de lui-même.																						*
*																														*
************************************************************************************************************************/
function NodesMissedWakeUp($apizwave, $logTimeLine) {

	$time_now = time();
	$url_health = 'http://localhost:8083/network?type=info&info=getHealth&apikey=' . $apizwave;
	$content = (file_get_contents($url_health));
	//mg::message('', $content);
	$results = json_decode($content, true);
	$success = $results["state"];
	if ($success != 'ok') {
		mg::message('', 'ZAPI network getHealth return une erreur : ' . $results["result"]);
	} else {
		// get the full node list
		$devices = $results["result"]["devices"];
		$node_errors = array();
		foreach ($devices as $nodeId => $node_values) {
			$next_wakeup = 0;
			// listening devices work on sector
			$isListening = $node_values["data"]["isListening"]["value"];
			// device can be disabled from jeedom
			$enabled = $node_values["data"]["is_enable"]["value"];
			// test only if node is enable and is battery powered
			if ($enabled & $isListening == 0) {
				// get the wake up interval
				$wakeup_interval = $node_values["data"]["wakeup_interval"]["value"];
				if ($wakeup_interval == 0) {
					// this device never wakeup by itself, continue
					continue;
				}
				// check last notification received for this node
				$next_wakeup = $node_values["data"]["wakeup_interval"]["next_wakeup"];
				// check if node didn't wakeup as expected.
				if ($next_wakeup != 0 && $next_wakeup < $time_now) {
					// special case if the device is currently mark as awake
					$isAwake = $node_values["data"]["isAwake"]["value"];
					if ($isAwake) {
						$last_notification = $node_values["last_notification"]["receiveTime"]["value"];
						// check if the node has been awake for more than 5 minutes
						if ($last_notification + 300 < $time_now) {
							// this node seems awake for too long, we're going to ping
							$url = 'http://localhost:8083/node?nodeId=' . $nodeId . '&type=action&action=testNode&apikey=' . $apizwave;
							file_get_contents($url);
							continue;
						}
					}
					// get the name of the device
					$node_name = $node_values["data"]["description"]["name"];
					// add a log entry
					mg::message('', 'NodeId ' . $nodeId . ' ' . $node_name);
					// add nodeId to the node list
					$node_errors[] = $nodeId;
				}
			}
		}
		if (count($node_errors) == 0) {
			mg::unsetVar('_Nodes_Wakeup_Erreur');
			mg::messageT('', '! Aucun module sur batterie ayant un WakeUp en retard');
		} else {
			$listNodes = implode(',', $node_errors);
			mg::setVar("_Nodes_Wakeup_Erreur", $listNodes);
			mg::messageT('', "! Les modules sur batterie suivant ($listNodes) ont leurs WakeUp dépassés !!!");
			mg::message($logTimeLine, "Zwave - Les modules sur batterie suivant ($listNodes) ont leurs WakeUp dépassés !!!");
		}
	}
}

/************************************************************************************************************************
* 														BatteriesRefresh												*
*************************************************************************************************************************
* Demande une mise à jour forcée de du niveau des piles si plus ancien que dayOld										*
*																														*
************************************************************************************************************************/
function BatteriesRefresh($apizwave, $dayOld=-7) {
	global $scenario;
	$minimum_date = strtotime("-$dayOld day", time());

	// call the network health endpoint
	$url = 'http://localhost:8083/network?type=info&info=getHealth&apikey=' . $apizwave;
	$content = file_get_contents($url);
	//$scenario->setLog($content);
	// get result as json
	$results = json_decode($content, true);
	$success = $results["state"];
	if ($success != 'ok') {
		mg::messageT('', 'ZAPI network getHealth return une erreur : ' . $results["result"]);
	} else {
		// get the full node list
		$devices = $results["result"]["devices"];
		$node_errors = array();

		foreach ($devices as $nodeId => $node_values) {
			// listening devices work on sector
			$isListening = $node_values["data"]["isListening"]["value"];
			// device can be disabled from jeedom
			$enabled = $node_values["data"]["is_enable"]["value"];
			// test only if node is enable and is battery powered
			if ($enabled & $isListening == 0) {
				$can_wake_up = $node_values["data"]["can_wake_up"]["value"];
				// check if device can wakeup
				if ($can_wake_up) {
					// get the last battery report date
					$last_battery_report_date = $node_values["instances"]["1"]["commandClasses"]["128"]["data"]["0"]["updateTime"];
					// check if the report occure after the minimum date allowed
					if ($last_battery_report_date < $minimum_date) {
						if (count($node_errors) == 0) {
							mg::messageT('', '******* Battery level not updated *******');
						}
						// get the name of the device
						$node_name = $node_values["data"]["description"]["name"];
						// add a log entry
						mg::Message('', "NodeId $nodeId $node_name " . gmdate("d.m.Y", $last_battery_report_date));
						// add nodeId to the node list
						$node_errors[] = $nodeId;
						// then we ask for a manuel refresh of the battery level
						$url = "http://localhost:8083/node?type=refreshData&node_id=$nodeId&instance_id=1&cc_id=128&index=0&apikey=$apizwave";
						$content = file_get_contents($url);
						$results = json_decode($content, true);
						$success = $results["state"];
						if ($success != 'ok') {
							mg::Message('', "node refreshData return aune erreur : " . $results["result"]);
						} else {
							mg::Message('', "   -> Request for updating the battery level");
						}
					}
				}
			}
		}
		if (count($node_errors) == 0) {
			mg::MessageT('', "! Aucun noeud avec un % de batterie MàJ il y a plus de $dayOld jours.");
		} else {
			mg::MessageT('', "! Les nodes " . implode(',', $node_errors) . " ont leurs % de batteries depuis plus de $dayOld jours.");
		}
	}
}

/************************************************************************************************************************
* 													ReviveDeadNodes														*
*************************************************************************************************************************
* Mise à jour des nœuds voisins																							*
* Une autre fonctionnalité qui a été désactivée dans Jeedom et toujours pour des raisons de trafic réseau inutile, 		*
* spécialement si votre réseau est stabilisé ou que vous ne déplacez jamais vos modules.								*
																														*
* Dans l’éventualité ou vous apportez des changements dans la topologie de votre réseau, il sera alors intéressant 	*
* durant quelques semaines de forcer des mises à jour des routes afin d’affiner au mieux la qualité de votre maillage et*
* calcul des sauts afin de rejoindre le contrôleur.																		*
************************************************************************************************************************/
function HealNetwork($apizwave) {
	global $scenario;
	// Setup
	// Jeedom configuration/API/Clef API Z-Wave
//	$apizwave = 'yourZwaveAPIKey';
	// End Setup

	// call the network health endpoint
	$url_health = 'http://localhost:8083/controller?type=action&action=healNetwork&apikey=' . $apizwave;
	$content = file_get_contents($url_health);
	mg::ZwaveBusy(5);
//	$scenario->setLog($content);
	mg::message($logTimeLine, $content);
	// get result as json
	$results = json_decode($content, true);
	$success = $results["state"];
	if ($success != 'ok') {
		$scenario->setLog('ZAPI controller healNetwork return une erreur: ' . $results["result"]);
	}
}

?>