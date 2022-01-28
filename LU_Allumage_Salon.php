<?php
/**********************************************************************************************************************
Allumage Salon - 44
Scénario permettant de gérer une série de lampes avec un seul visuel comportant l'intensité demandée,
l'ambiance (parmis plusieurs).
l'allumage est déclenché par un mouvement dans la pièce SI il fait nuit.
L'extinction par l'absence de mouvement OU l'apparition du jour OU par la télécommande OU par interrupteur.
Un réglage de l'ambiance est dispo sur le widget pour les séances cinéma par exemple.
La gestion de la luminosité lié à l'activité : au bout de $TimeAvantBaisse on baisse l'intensité jusqu'à 0 à TimeOut (60)
Le retour état du Stop dans le widget est à régler à TimeOut + 5 au minimum pour que les lampes ne se rallument pas juste après l'extinction.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
	//	$infNbMvmtSalon, $infBoutonOnOff
	//	$equipEcl
	//	$tabLampes	Tableau des commandes de l'état des lampes à gérer, de leur intensité max et des intensités "Ambiance"

// N° des scénarios :

//Variables :
	$alarme = mg::getVar('Alarme');
	$nuitSalon = mg::getVar('NuitSalon');
	$lastMvmt = round(mg::lastMvmt($infNbMvmtSalon, $nbMvmt)/60);

	$memoEtat = mg::getCmd($equipEcl, 'Memo Etat');
	$newIntensite = mg::getCmd($equipEcl, 'Lampe Générale Etat');
	$ambiance = mg::getcmd($equipEcl, 'Ambiance');
//	$boutonEvent = mg::getCmd($infBoutonEvent);

// Paramètres :
	$seuilNbMvmt = 2; //mg::getParam('Lumieres', 'seuilNbMvmt');		// Nb de mouvement minimum pour provoquer le réallumage de nuit
	$timeOutSalon = mg::getParam('Lumieres', 'timeOutSalon');	// Durée en mn avant extinction des lumières du salon si pas de mouvement
	$incIntensiteUp = mg::getParam('Lumieres', 'incIntensiteUp');
	$incIntensiteDown = mg::getParam('Lumieres', 'incIntensiteDown');
	$intensiteMininimum = mg::getParam('Lumieres', 'intensiteMininimum');
	$timerProgressif = 5;										// Timer en mn des incrément d'intensité automatique
	$logTimeLine = mg::getParam('Log', 'timeLine');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
mg::setCron('', '*/3 * * * *');

$boutonEvent = mg::declencheur(mg::toHuman($infBoutonEvent)) ? mg::getCmd($infBoutonEvent) : '';
 
$nomDeclencheur = mg::declencheur('', 3);

// Extinction lampes
if ($alarme == 1 || $nuitSalon != 1 || $lastMvmt >= $timeOutSalon || $boutonEvent == 'double' || $newIntensite == 0) {
		//=============================================================================================================
		mg::MessageT('', "! EXTINCTION (MANUELLE OU AUTOMATIQUE)");
		//=============================================================================================================
		$newIntensite = 0;
}

// Allumage manuel ou reprise de mouvement
if (( $nuitSalon != 0 && $memoEtat < $intensiteMininimum && $nbMvmt >= $seuilNbMvmt) || $boutonEvent == 'single') {
	//=================================================================================================================
	mg::MessageT('', "! ALLUMAGE MANUEL OU AUTOMATIQUE");
	//=================================================================================================================
//	$newIntensite = ($nuitSalon == 1 ? $intensiteMininimum : 0);
	$newIntensite = ($nbMvmt >= $seuilNbMvmt || $nuitSalon == 1) ? $intensiteMininimum : 0;
}

// gestion progressif si ambiance > 0
if ($memoEtat > 0 && $newIntensite > 0 && $ambiance > 0) {
	if ($nbMvmt >= $seuilNbMvmt || ($nomDeclencheur == 'schedule' && $lastMvmt < $timerProgressif)) {
		//=============================================================================================================
		mg::MessageT('', "! AUGMENTATION AUTO. DE L'INTENSITE nbMvmtSalon ($nbMvmt) >= $seuilNbMvmt ou lastMvmtSalon ($lastMvmt) < $timerProgressif");
		//=============================================================================================================
		$newIntensite = round(min($memoEtat*(1+$incIntensiteUp), 99), 0);

	} if ($nomDeclencheur == 'schedule' && $lastMvmt >= $timerProgressif) {
		//=============================================================================================================
		mg::MessageT('', "! DIMINUTION AUTO. DE L'INTENSITE lastMvmtSalon ($lastMvmt) >= $timerProgressif");
		//=============================================================================================================
		$newIntensite = round(max($memoEtat*(1-$incIntensiteDown), $intensiteMininimum), 0);
	}
}

// Mise à jour état des lampes
	//=================================================================================================================
	mg::MessageT('', "! MODIFICATION D'INTENSITE - memoEtat ==> $memoEtat : newIntensite => $newIntensite - Ambiance N° $ambiance - NomDeclencheur : $nomDeclencheur)");
	//=================================================================================================================
if ($newIntensite != $memoEtat || $nomDeclencheur == 'Lampe Générale Etat' || $nomDeclencheur == 'Ambiance' || $nomDeclencheur == 'schedule' || $nomDeclencheur == 'user') {
	PiloteLampes($equipEcl, $tabLampes, $newIntensite, $ambiance);
}

//=====================================================================================================================
mg::MessageT('', ". FIN DE PROCESS - newintensite : $newIntensite");
//=====================================================================================================================

// Extinction finale
if (($newIntensite < 1 && ($memoEtat > 0) || $boutonEvent == 'double')) {
	// Attente absence de mouvement pendant 2 mn plus sleep(120) avant sortie finale et ainsi éviter une relance précoce par NuitSalon ou 'schedule'
	mg::message('', "Attente de 2 mn sans mouvement ...");
	mg::wait("$infNbMvmtSalon == 0", 120);
	mg::Message($logTimeLine, "Extinction du salon terminé.");

// Au premier allumage complet
} elseif ($newIntensite > 0 && ($nomDeclencheur == 'Lampe Générale Etat' || $boutonEvent == 'single')) {
	mg::Message($logTimeLine, "Allumage du salon terminé.");
}

/********************************************* PILOTE DES LAMPES ******************************************************
Permet de recopier l'état, pondéré par l'ambiance et le Max, vers les différentes lampes
**********************************************************************************************************************/
function PiloteLampes($equipEcl, $tabLampes, $intensite, $ambiance) {
	// Boucle des lampes
	for ($i = 0; $i < count($tabLampes); $i++) {
		$details_Lampe = explode(':', $tabLampes[$i]);
		$intensiteAmbiance = max(0, intval($details_Lampe[$ambiance + 1]));

		// Lampe à variateur
		if (mg::existCmd($details_Lampe[0], 'Slider Intensité')) {
			if (mg::existCmd($details_Lampe[0], 'Etat_Intensité')) {
				$maxValue = mg::getMinMaxCmd($details_Lampe[0], 'Etat_Intensité', 'max');
				if ($maxValue == 0) $maxValue = 254;
			} 
			$newIntensite = min($maxValue, round(($intensite/99 * $intensiteAmbiance/99 * $maxValue)));
			mg::setCmd($details_Lampe[0], 'Slider Intensité', $newIntensite);
			
		// Lampe On/Off
		} else {
			if ($intensiteAmbiance > 0 && $intensite > 0) mg::setCmd($details_Lampe[0], 'On');
			elseif ($intensiteAmbiance == 0 && $intensite == 0) mg::setCmd($details_Lampe[0], 'Off');
		}
		usleep(0.5 * 1000000);
//		mg::message('',"$details_Lampe - $intensite/$newIntensite - $maxValue - $intensiteAmbiance");
	}
	mg::setInf($equipEcl, 'Memo Etat', $intensite);
	mg::setCmd($equipEcl, 'Lampe Générale Slider', $intensite);
}

?>