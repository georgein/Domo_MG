<?php
/************************************************************************************************************************
LU_Allumage_SdB - 199

Déclencheur nbMvmt à "toujours répéter"
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
	// $equipLampes, $equipVeilleuse
	// $infPorte

//N° des scénarios :

// Variables :
	$nuitSalon = mg::getVar('nuitSalon');
	$heureReveil = mg::getVar('heureReveil'); 
	$nePasDeranger = mg::getVar('nePasDeranger', 0);
	$etatPorte = mg::getCmd($infPorte);

//Paramètres :
	$timer = 5; // Durée en mn avant extinction sans mouvement si porte ouverte

/**********************************************************************************************************************
**********************************************************************************************************************/
//	$nePasDeranger_2 = mg::TimeBetween($heureReveil, time(), $heureReveil+90*60); 
	$timeVoletsNuit = mg::getParam('Volets', 'timeVoletsNuit');
	$nePasDeranger = mg::TimeBetween(strtotime($timeVoletsNuit), time(), $heureReveil-60*60);

	mg::messageT('', "NuitSalon : $nuitSalon - Porte : $etatPorte - nePasDeranger : $nePasDeranger/$nePasDeranger_2");

// Porte ouverte on éteint
if ($etatPorte == 0) {
	mg::setCmd($equipLampes, 'Off');
	mg::setCmd($equipVeilleuse, 'Off');
		mg::setCmd($equipVeilleuse, 'Slider Intensité', 0);
		mg::setCmd($equipLampes, 'Slider Intensité', 0);
	mg::messageT('', "EXTINCTION");

	
// Porte FERMEE on allume
} else {

	// La nuit OU PasDéranger
    if ($nuitSalon == 2 || ($nePasDeranger == 1 /*&& $nePasDeranger_2 == 0*/)) {
		mg::setCmd($equipVeilleuse, 'Slider Intensité', 10);
		mg::setCmd($equipLampes, 'Off');
	// La journée
	} else {
		mg::setCmd($equipLampes, 'Slider Intensité', 254);
		mg::setCmd($equipLampes, 'On');
		mg::setCmd($equipVeilleuse, 'Slider Intensité', 254);
		mg::setCmd($equipVeilleuse, 'On');
	}
	mg::messageT('', "ALLUMAGE");
}

// Radio SdB pour finir
if (mg::declencheur('Ouverture') && $nuitSalon < 2 && $nePasDeranger == 0) {
	$IP_JPI = 'HTTP://'.mg::getValSql('_tabUsers', 'JPI', '', 'IP').':8080';
	file_get_contents("$IP_JPI/?action=_radioSdB");
}

mg::setCron('', "*/$timer * * * *");

?>