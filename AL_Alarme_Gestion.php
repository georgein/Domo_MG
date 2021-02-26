<?php
/**********************************************************************************************************************
Alarme Gestion - 64+

Scénario de gestion de l'alarme.
Méthode :	on passe en "Alerte" et en "Signal" durant l'actionnement d'un capteur
	on passe en "Alarme" si Signal est confirmé ($DureeAvantAlarme)
	on sort de "Alarme" et "Alerte" si "Signal" il y a plus de $DureeMaxSansSignal
	on envoi des Message si "Alarme" tous $_TimingAlarme secondes
Les actions de fin de nuit sont inhibées à la première tombée du jour

Sirène Popp :
- Paramètre n° 5
- Valeur 0 : Sirène seule
- Valeur 1 : Flash seul
- Valeur 2 : Flash + Sirène

**********************************************************************************************************************/
//Infos, Commandes et Equipements :
//	$infoPorteEntree
//	$equipAlarme, $equipLampeCouleur, $equipSirene, $equipGeneralMaison 


//N° des scénarios :

// Variables :
	$alarme = mg::getVar('Alarme');
	$nbMvmt = mg::getCmd($equipGeneralMaison, 'NbMvmt');
	$nbPortes = mg::getCmd($equipGeneralMaison, 'NbPortes');

//Paramètres :
	$logAlarme = mg::getParam('Log', 'alarme');					// Pour debug
	$alarmeJingle = mg::getParam('Alarme', 'jingle');
	$alerteNbMvmt = mg::getParam('Alarme', 'alerteNbMvmt');		// Nb total de mouvements provoquant l'alerte.
	$alerteTimeOut = mg::getParam('Alarme', 'alerteTimeOut');		// Durée (en sec) sans Signal pour stopper l"alerte.
	$alarmeTimeOut = mg::getParam('Alarme', 'alarmeTimeOut');		// Durée (en sec) sans Signal pour stopper l"alarme.
	$destinatairesAlarme = mg::getParam('Alarme', 'destinataires');	// Destinataires des messages d'alarme

	$alarme_Perimetrique = mg::getCmd($equipAlarme, 'Perimetrique Etat');
	$inhibition = mg::getCmd($equipAlarme, 'Inhibition Etat');
	$sirène = mg::getCmd($equipAlarme, 'Sirène Etat');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/

if ($inhibition) {
	$alerte_Debut = 0;
	$alarme_Debut = 0;
	return;
}

$destinatairesAlarme = "LogAlarme,$destinatairesAlarme";
$message = '';

// Récupération des variables
$alerte_Debut = mg::getVar('_Alerte_Debut', 0);
$alarme_Debut = mg::getVar('_Alarme_Debut', 0);

$porteEntree = mg::getCmd($infoPorteEntree, '');

// En alerte si en anomalie jusqu'à plus d'anomalie pendant $alerteTimeOut
//---------------------------------------------------------------------------------------------------------------------
mg::Message('', "--------------------------------------- GESTION ALERTE ---------------------------------------------");
//---------------------------------------------------------------------------------------------------------------------
$anomalie = ($nbPortes > 0 && $alarme_Perimetrique) || $porteEntree || $nbMvmt >= $alerteNbMvmt;
if ($anomalie && !$alerte_Debut) {
	// Positionnement du début d'alerte
	$alerte_Debut = time();
	$message = "DEBUT D'ALERTE";
}
// RaZ de l'alerte
if (!$anomalie && $alerte_Debut && (time() - $alerte_Debut) > $alerteTimeOut) {
		$message = "FIN D'ALERTE.";
	$alerte_Debut = 0;
	}

// En alarme si en Alerte jusqu'à plus d'Alerte pendant $alarmeTimeOut
//---------------------------------------------------------------------------------------------------------------------
mg::Message('', "--------------------------------------- GESTION ALARME ---------------------------------------------");
//---------------------------------------------------------------------------------------------------------------------
// Positionnement du début d'alarme
if ($alerte_Debut && !$alarme_Debut && (time() - $alerte_Debut) > $alerteTimeOut)
	{
		$alarme_Debut = time();
		$message = "DEBUT D'ALERTE.";
		mg::LampeCouleur($equipLampeCouleur, 80, mg::ROUGE);
		if ($sirène) {
			//mg::setCmd($equipSirene, 'All');
			//mg::setCmd($equipSirene, 'On');
			mg::GoogleCast('PLAY', $alarmeJingle, 100);
		} else {
			//mg::setCmd($equipSirene, 'Flash');
			//mg::setCmd($equipSirene, 'On');
		}
	}
// RaZ de l'Alarme
if (!$alerte_Debut && $alarme_Debut && (time() - $alarme_Debut) > $alarmeTimeOut) {
		if ($sirène) {
			//mg::setCmd($equipSirene, 'Off');
		}
		mg::LampeCouleur($equipLampeCouleur, 0);
		$message = "FIN D'ALERTE.";
		$alarme_Debut = 0;
	}

// Envoi des Messages
if ($message) {
	mg::Message($logAlarme, $message);
	if (strpos($message, 'ALARME') !== false) { mg::Message($destinatairesAlarme, $message); }
}

//---------------------------------------------------------------------------------------------------------------------
//---------------------------------------------------------------------------------------------------------------------

// Mémo des variables
mg::setVar('_Alerte_Debut', $alerte_Debut);
mg::setVar('_Alarme_Debut', $alarme_Debut);

?>