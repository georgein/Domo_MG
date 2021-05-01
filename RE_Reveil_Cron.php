<?php
/**********************************************************************************************************************
Reveil_Cron - 47
Scénario permettant le réveil journalier pouvant être modifié manuellement chaque jour ou inhibé.
Prévoir une relance de ce scénario à chaque modification manuelle du reveil.
IMPORTANT : Le script DOIT être lancé après 00:00, soit le jour du réveil, pour 'coller' aux agendas.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
//	$equipReveil

// N° des scénarios :
	$ScenarioReveil = 48;
//Variables :
	$tab_ReveilExceptions = array(		// Tableau des exception horaire par jour (laisser vide si pas d'exception).Format xx:xx
			'Dimanche	;08:30',
			'Lundi		;',
			'Mardi		;',
			'Mercredi	;08:30',
			'Jeudi		;08:30',
			'Vendredi	;08:30',
			'Samedi		;08:30'
	);

// Paramètres :
	$logTimeLine = mg::getParam('Log', 'timeLine');
	$reveilHeureNormale = mg::getParam('Reveil', 'heureNormale');			// Heure réveil normale (travail ou rando au format xx:xx)
	$reveilHeureVacance = mg::getParam('Reveil', 'heureVacance');			// Heure réveil en Vacance au format xx:xx

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/

// Si Set manuel on positionne la variable '_ReveilManuel' pour ne pas faire la première demande de calcul automatique
if (mg::declencheur('Heure_Reveil')) {
	if ( mg::getVar('_ReveilManuel') == 1 && time() < mg::getVar('_Heure_Reveil') ) {
		mg::unsetVar('_ReveilManuel');
		return;
	}
}

//=====================================================================================================================
// Init (doit être lancé après 00:00)
$jour =mg::getTag('#njour#');
$jour = ($jour > 6) ? 0 : $jour;
$detailsTabExceptions = explode(';', $tab_ReveilExceptions[$jour]);

// Lecture des agendas, WE et tableau d'exceptions
if (!mg::declencheur('Heure_Reveil')) {
mg::Message('', "-------------------------------------- LECTURE AGENDAS --------------------------------------------");
	$alarmTime = $reveilHeureNormale;

	// Jours fériés
	$rdvs = mg::getICS(mg::getParam('Global', 'ICS_FERIES'), strtotime("-0 day"), strtotime("now"));
	if (is_array($rdvs) && count($rdvs) > 0) {
		$alarmTime = $reveilHeureVacance;
		mg::message($logTimeLine, "Réveil : Jours fériés {$rdvs[1]['title']}($alarmTime)");
	}

	// Vacances scolaires et maladie
	$rdvs = mg::getICS(mg::getParam('Global', 'ICS_NR'), strtotime("-0 day"), strtotime("now"));
	if (is_array($rdvs) && count($rdvs) > 0) {
		foreach ($rdvs as $i => $line) {
			if ( strripos($line['title'], 'Vacance') !== false || strripos($line['title'], 'maladie') !== false ) {
				$alarmTime = $reveilHeureVacance;
				mg::message($logTimeLine, "Réveil : NR - {$line['title']} ($alarmTime)");
			}
		}
	}
	// Horaire de WE
	if ( $jour == 0 or $jour == 6 ){
		$alarmTime = $reveilHeureVacance;
	}

	// Gestion des exceptions (rando et autres)
	if ( trim($detailsTabExceptions[1]) != '' ) { 
		$alarmTime = trim($detailsTabExceptions[1]);
		mg::message('', "Tableau d'exceptions : $alarmTime");
	}
	
	$myDate = strtotime($alarmTime);

// Si mise à jour manuelle des sets on lis les set
} else {
mg::Message('', "------------------------------------- LECTURE DU SLIDER -------------------------------------------");
	$myDate = mg::getCmd($equipReveil, 'Heure_Reveil')/1000;
}

// Gestion du mode On/Off
if (mg::getCmd($equipReveil, 'Stop_Réveil') == 'off') { 
	mg::setScenario($ScenarioReveil, 'deactivate'); 
	
} else {
mg::Message('', "------------------------------------- ACTIVATION REVEIL -------------------------------------------");
// Calcul / Rafraichissement du réveil
// Correction du jour de l'alarme à programmer 
	if ($myDate < time()) { $myDate = $myDate + (24*3600); }
	
	mg::setVar('_Heure_Reveil', $myDate);
	if (!mg::declencheur('Heure_Reveil')) { mg::setCmd($equipReveil, 'Reveil_Slider', $myDate*1000); }
	mg::unsetVar('_ReveilOnLine');
	
	mg::setScenario($ScenarioReveil, 'activate');
	mg::setCron($ScenarioReveil, $myDate);
	mg::Message($logTimeLine, "Reveil - Programmé pour " . date('d/m/Y H:i', $myDate));
}
?>