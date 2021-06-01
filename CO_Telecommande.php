<?php
/**********************************************************************************************************************
Télécommande - 74
Gestion des actions de la télécommande
Fonctions prise en charge :
	Bt1 long : Arret/Marche Sonos
	Bt1/Bt2 court : Diminution/Augmentation volume Sonos
	Bt3 Long : Arret/Marche lumière extérieure
	Bt3/Bt4 court : Diminution/Augmentation lumière salon
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
//	$equipEcl, $equipEclExt

// N° des scénarios :

//Variables :
	$incrementSon = 0.25;										// Incrément de modification du son en %
	$incrementLumiere = 0.10;									// Incrément de modification de la lumière en %

// Paramètres :
	$equipSonos = mg::getParam('Media', 'equipSonos');
	$reveilStationRadio = mg::getParam('Reveil', 'stationRadio');		// Nom de la radio à lancer
	$reveilVolumeRadio = mg::getParam('Reveil', 'volumeRadio');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
$sonosEnCours = mg::getCmd($equipSonos, 'Status');
$memoEtat = mg::getCmd($equipEcl, 'Memo Etat');

$numBouton = mg::getCmd($infoNumBouton);
mg::Message('', "Bouton N°$numBouton");
// BOUTON 1 Long : MISE EN ROUTE/EXTINCTION SONOS
if ($numBouton == 2) {
	mg::Message('', "Met en route ou arrète la radio de Sonos");
	if ( $sonosEnCours != 'Lecture') {
		mg::setCmd($equipSonos, 'Volume', $reveilVolumeRadio);
		mg::setCmd($equipSonos, 'Jouer une radio', '.', $reveilStationRadio);
	} else {
		mg::setCmd($equipSonos, 'Stop');
		mg::setCmd($equipSonos, 'Volume', 100);
		return;
	}
}
	// BOUTON 1 Court :DIMINUTION SONOS
elseif ($numBouton == 1) {
	// Diminution Sonos
	if ($sonosEnCours) {
		$volume = mg::getCmd($equipSonos, 'Volume status') * (1 - $incrementSon);
		if ($volume < 0) { $volume = 0; }
		mg::Message('', "Diminue le volume de Sono à $volume %");
		mg::setCmd($equipSonos, 'Volume', $volume);
		return;
	}
}

// BOUTON 2 Court : AUGMENTATION SONOS
elseif ($numBouton == 3) {
	// Augmentation Sonos
	if ($sonosEnCours) {
		$volume = mg::getCmd($equipSonos, 'Volume status') * (1 + $incrementSon);
		if ($volume >= 99) { $volume = 100; }
		mg::Message('', "Augmente le volume de Sono à $volume %");
		mg::setCmd($equipSonos, 'Volume', $volume);
	}
}

// BOUTON 2 Long :
elseif ($numBouton == 4) {
	
}

/*********************************************************************************************************************/
/*********************************************************************************************************************/

// BOUTON 3 Long : ALLUMAGE/EXTINCTION EXTERIEUR
elseif ($numBouton == 6) {
	if (mg::getCmd($equipEclExt, 'Générale Extérieure_Etat') == 0) {
			mg::setCmd($equipEclExt, 'Générale Extérieure_On');
	} else {
		mg::setCmd($equipEclExt, 'Générale Extérieure_Off');
	}
}

	// BOUTON 3 Court : DIMINUTION LUMIERE
elseif ($numBouton == 5) {
	$memoEtat = $memoEtat * (1 - $incrementLumiere);
	if ($memoEtat < 0) { $memoEtat = 0; }
	mg::setCmd($equipEcl, 'Lampe Générale Slider', $memoEtat);
	mg::Message('', "Diminue l'intensité à $memoEtat %");
}

// BOUTON 4 Court : AUGMENTATION LUMIERE
elseif ($numBouton == 7) {
	if ($memoEtat == 0) { $memoEtat = 10; }
	$memoEtat = $memoEtat * (1 + $incrementLumiere);
	if ($memoEtat >= 99) { $memoEtat = 99; }
	mg::setCmd($equipEcl, 'Lampe Générale Slider', $memoEtat);
	mg::Message('', "Augmente l'intensité à $memoEtat %");
}
	
// BOUTON 4 Long :
elseif ($numBouton == 8) {

}

?>