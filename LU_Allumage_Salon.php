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
	$boutonOnOff = mg::getCmd($infBoutonOnOff);

// Paramètres :
	$seuilNbMvmt = mg::getParam('Lumieres', 'seuilNbMvmt');		// Nb de mouvement minimum pour provoquer le réallumage de nuit
	$timeOutSalon = mg::getParam('Lumieres', 'timeOutSalon');	// Durée en mn avant extinction des lumières du salon si pas de mouvement
	$incIntensiteUp = mg::getParam('Lumieres', 'incIntensiteUp');
	$incIntensiteDown = mg::getParam('Lumieres', 'incIntensiteDown');
	$intensiteMininimum = mg::getParam('Lumieres', 'intensiteMininimum');
	$timerProgressif = 5;										// Timer en mn des incrément d'intensité automatique
	$logTimeLine = mg::getParam('Log', 'timeLine');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
mg::setCron('', time() + $timeOutSalon*60);

//$nuitSalon = 1; ***** POUR DEBUG *****
$nomDeclencheur = mg::ExtractPartCmd(mg::getTag('#trigger#'), 3);

// Extinction lampes
if ($alarme != 0 || $nuitSalon == 0 || $lastMvmt >= $timeOutSalon || $boutonOnOff == 1004) {
		//=============================================================================================================
		mg::MessageT('', "! EXTINCTION MANUELLE OU AUTOMATIQUE");
		//=============================================================================================================
		$newIntensite = 0;
}

// Allumage manuel ou reprise de mouvement
if (( $nuitSalon != 0 && $memoEtat < $intensiteMininimum && $nbMvmt >= $seuilNbMvmt) || $boutonOnOff == 1002) {
	//=====================================================================================================================
	mg::MessageT('', "! ALLUMAGE MANUEL OU AUTOMATIQUE");
	//=====================================================================================================================
	$newIntensite = $intensiteMininimum;
}

// gestion progressif si ambiance >= 1
if ($memoEtat > 0 && $newIntensite > 0 && $ambiance >= 1) {
	if ($nbMvmt >= $seuilNbMvmt+1 || ($nomDeclencheur == 'schedule' && $lastMvmt < $timerProgressif)) {
		//=====================================================================================================================
		mg::MessageT('', "! AUGMENTATION AUTO. DE L'INTENSITE nbMvmtSalon ($nbMvmt) >= $seuilNbMvmt ou lastMvmtSalon ($lastMvmt) < $timerProgressif");
		//=====================================================================================================================
		$newIntensite = round(min($memoEtat*(1+$incIntensiteUp), 99), 0);

	} if ($nomDeclencheur == 'schedule' && $lastMvmt >= $timerProgressif) {
		//=====================================================================================================================
		mg::MessageT('', "! DIMINUTION AUTO. DE L'INTENSITE lastMvmtSalon ($lastMvmt) >= $timerProgressif");
		//=====================================================================================================================
		$newIntensite = round(max($memoEtat*(1-$incIntensiteDown), $intensiteMininimum), 0);
	}
}

// Mise à jour état des lampes
if ($newIntensite != $memoEtat || $nomDeclencheur == 'Lampe Générale Etat' || $nomDeclencheur == 'Ambiance' || $nomDeclencheur == 'schedule' || $nomDeclencheur == 'user') {
//=====================================================================================================================
mg::MessageT('', "! MODIFICATION D'INTENSITE - memoEtat ==> $memoEtat : newIntensite => $newIntensite - Ambiance N° $ambiance - NomDeclencheur : $nomDeclencheur)");
//=====================================================================================================================
	PiloteLampes($equipEcl, $tabLampes, $newIntensite, $ambiance);
}

//=====================================================================================================================
mg::MessageT('', ". FIN DE PROCESS - newintensite : $newIntensite");
//=====================================================================================================================
mg::setInf($equipEcl, 'Memo Etat', $newIntensite);

// Extinction finale
	if (($newIntensite < 1 && $memoEtat > 0) || $boutonOnOff == 1004) {
		// Attente absence de mouvement pendant 2 mn plus sleep(120) avant sortie finale et ainsi éviter une relance précoce par NuitSalon ou 'schedule'
		mg::message('', "Attente de 5 mn sans mouvement ...");
	mg::wait("$infNbMvmtSalon == 0", 120);
	sleep(120);
	mg::Message('', "Extinction du salon terminé.");

// Au premier allumage complet
} elseif ($newIntensite > 0 && ($nomDeclencheur == 'Lampe Générale Etat' || $boutonOnOff == 1002)) {
	mg::Message('', "Allumage du salon terminé.");
}

/********************************************* PILOTE DES LAMPES ******************************************************
Permet de recopier l'état, pondéré par l'ambiance, vers les différentes lampes
**********************************************************************************************************************/
function PiloteLampes($equipEcl, $tabLampes, $intensité, $ambiance) {
	// Boucle des lampes
	$cptMax = 10; // Nb de tentatives max
	$cpt = 0;

	reprise:
	$resteAFaire = 0;
	// Réglage de l'état des lampes selon l'Intensité générale et l'ambiance
	for ($i = 0; $i < count($tabLampes); $i++) {
		$details_Lampe = explode(':', $tabLampes[$i]);
		$etatLampe = mg::getCmd($details_Lampe[0], 'Etat');
		$maxValue = mg::getMinMaxCmd($details_Lampe[0], 'Etat', 'max');
		if (!mg::isActive($details_Lampe[0])) { mg::setEquipement($details_Lampe[0], 'activate'); } // Pour deconz

		$intensiteLampe = max(0, intval($details_Lampe[$ambiance + 1]));
		$lum_Max_Lampe = min(254, round(($intensité/99 * $intensiteLampe/99 * (($maxValue > 0) ? $maxValue : 99))));

try {
			// Lampe slider
		if (mg::existCmd($details_Lampe[0], 'Slider Intensité')) {
			if ($etatLampe != $lum_Max_Lampe) {
				@mg::setCmd($details_Lampe[0], 'Slider Intensité', $lum_Max_Lampe);
				$resteAFaire++;
			}
		// Lampes sans slider : UNIQUEMENT OFF
		} else {
			if ($lum_Max_Lampe == 0 && ($etatLampe > 0 || (mg::existCmd($details_Lampe[0], 'Puissance') && mg::getCmd($details_Lampe[0], 'Puissance') > 2))) {
				@mg::setCmd($details_Lampe[0], 'Off');
				$resteAFaire++;
			}
		}

} catch(Exception $e){
 	mg::message($logTimeLine, "CATCH ERROR sur ".$details_Lampe[0]." => ".$e->getMessage());
	goto reprise;
}

	}
	// On attend queue Zwave == 0 et si reste à faire != 0 on relance
	$cpt++;
	mg::message('', "****** Intensité : $intensité - reste à Faire : $resteAFaire - cpt : $cpt : Attente retour Zwave ....");
	if ($resteAFaire > 0 && $cpt < $cptMax) {
		mg::ZwaveBusy(1);
		sleep(2);
		goto reprise;
	}
	mg::setCmd($equipEcl, 'Lampe Générale Slider', $intensité);
}

?>