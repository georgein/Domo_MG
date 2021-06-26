<?php
/**********************************************************************************************************************
ME Météo - 163

Plusieurs site d'extraction du METAR sont tentés pour pallier leurs indisponiblités éventuelles.
Importe le METAR de Marignane pour utiliser les infos de température, vent direction fafale, pression athmosphérique, humidité dans le virtuel de Météo
Cré le bulletin météo textuel avec les info Métar et du plugin Météo France.
**********************************************************************************************************************/
global $pathRef;

// Infos, Commandes et Equipements :
//	$equipMeteo, $equipMeteoFrance

// N° des scénarios :

//Variables :
//	$urlMeteoFrance = 'http://www.meteofrance.com/previsions-meteo-france/ensues-la-redonne/13820';

// Paramètres :
	$pathRef = mg::getParam('System', 'pathRef');	// Répertoire de référence de domoMG
	$codeMETAR  = mg::getParam('Confort', 'codeMETAR');		// Code de l'aéroport de référence (LFML pour Marignane)

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/

mg::setVar('_seuilVentFort', mg::getParam('Confort', 'seuilVentFort')); // Pour calcul dans commande [Extérieur][Temperature][ventFort]

// --------------------------------------------------------------------------------------------------------------------
// ----------------------------------------------EXTRACTION METAR MARIGNANE -------------------------------------------
// --------------------------------------------------------------------------------------------------------------------
$METAR = getMETAR ($codeMETAR);

if($METAR != '') {
	mg::setInf($equipMeteo, 'Heure METAR', $METAR['Heure_Locale']);
	mg::setInf($equipMeteo, 'Direction Vent METAR', $METAR['Vent_Direction']);
	mg::setInf($equipMeteo, 'Vitesse Vent METAR', $METAR['Vent_Vitesse']);
	mg::setInf($equipMeteo, 'Vitesse Rafales METAR', $METAR['Vent_Rafales']);
	mg::setInf($equipMeteo, 'Température METAR', $METAR['Temperature']);
	mg::setInf($equipMeteo, 'Humidité METAR', $METAR['Humidité']);
	mg::setInf($equipMeteo, 'Pression METAR', $METAR['Pression']);

	mg::setInf($equipMeteo, 'Direction Vent METAR', $METAR['Vent_Direction']);
	//print_r($METAR); // Pour debug
} else {
	mg::setmessageJeedom("Pas de METAR LISIBLE");
}

// ====================================================================================================================
// 																FIN
// ====================================================================================================================
mg::setVar('_MeteoLocale', MsgMeteoLocale($equipMeteo, $equipMeteoFrance));

/*********************************************************************************************************************
	* Examples de METAR pour tests :
	LFML 171400Z AUTO 23006KT 190V260 9999 ////// 23/11 Q1111
	LFML 302300Z AUTO 11003KT CAVOK 18/16 Q1014
	LFML 311730Z AUTO VRB02KT 9999 FEW022/// ///CB 22/15 Q1017 TEMPO BKN012
	LFML 311800Z AUTO 00000KT CAVOK 23/15 Q1017 BECMG SCT030 FEW040CB

	LFPO 041300Z 36020KT 320V040 1200 R26/0400 +RASH BKN040TCU 17/15 Q1015 RETS M2 26791299
	UMMS 231530Z 21002MPS 2100 BR OVC002 07/07 Q1008 R13/290062 NOSIG RMK QBB070
	UWSS 231500Z 14007MPS 9999 -SHRA BR BKN033CB OVC066 03/M02 Q1019 R12/220395 NOSIG RMK QFE752
	UWSS 241200Z 12003MPS 0300 R12/1000 DZ FG VV003CB 05/05 Q1015 R12/220395 NOSIG RMK QFE749
	UATT 231530Z 18004MPS 130V200 CAVOK M03/M08 Q1033 R13/0///60 NOSIG RMK QFE755/1006
	KEYW 231553Z 04008G16KT 10SM FEW060 28/22 A3002 RMK AO2 SLP166 T02780222
	EFVR 231620Z AUTO 19002KT 5000 BR FEW003 BKN005 OVC007 09/08 Q0998
	KTTN 051853Z 04011KT M1/2SM VCTS SN FZFG BKN003 OVC010 M02/M02 A3006 RMK AO2 TSB40 SLP176 P0002 T10171017=
	UEEE 072000Z 00000MPS 0150 R23L/0500 R10/1000VP1800D FG VV003 M50/M53 Q1028 RETSRA R12/290395 R31/CLRD// R/SNOCLO WS RWY10L WS
	RWY11L TEMPO 4000 RADZ BKN010 RMK QBB080 OFE745
	UKDR 251830Z 00000MPS CAVOK 08/07 Q1019 3619//60 NOSIG
	UBBB 251900Z 34015KT 9999 FEW013 BKN030 16/14 Q1016 88CLRD70 NOSIG
	UMMS 251936Z 19002MPS 9999 SCT006 OVC026 06/05 Q1015 R31/D NOSIG RMK QBB080 OFE745
**********************************************************************************************************************/

function getMETAR ($codeMETAR) {
	global $pathRef;
// ====================================================================================================================
mg::MessageT('', ". LECTURE METAR $codeMETAR");
// ====================================================================================================================
	$fileResult = "/var/www/html$pathRef/test.txt";
	$METAR = '';

// -------------------------------------------------------------------------------------------------------------
function Extrait_METAR($codeMETAR, $urlMETAR, $regex, $fileResult, $version) {
	$METAR_Brut = file_get_contents($urlMETAR);
	if (!$METAR_Brut) { return; }
	preg_match($regex, $METAR_Brut, $found);
	$METAR = trim($found[1]);
//	mg::setVar("METAR_V$Version", mg::getVar("METAR_V$Version") + 1);
//	mg::message('', "'$METAR' - V$Version=" . mg::getVar("METAR_V$Version",0) . " - '$urlMETAR'");
	return $METAR;
}

// -------------------------------------------------------------------------------------------------------------
	// REFERENCE METAR LFML DECODE : http://fr.allmetsat.com/metar-taf/france.php?icao=LFML

// ------------------- VERSION 2 --------------------------
	if( $METAR == '') {
				// http://tgftp.nws.noaa.gov/data/observations/metar/stations/LFML.TXT
		$urlMETAR = "http://tgftp.nws.noaa.gov/data/observations/metar/stations/$codeMETAR.TXT";
		$regex = '@.*\:\d*([.\s\S\w]{0,})$@';
		$METAR = Extrait_METAR($codeMETAR, $urlMETAR, $regex, $fileResult, 2);
	}
// ------------------- VERSION 1 --------------------------
	elseif( $METAR == '') {
				// https://www.aviationweather.gov/metar/data?ids=LFML&format=raw&date=0&hours=0
	$urlMETAR = "https://www.aviationweather.gov/metar/data?ids=$codeMETAR&format=raw&date=0&hours=0";
	$regex = "@<code>([.\s\S\w]{0,})</code>@";
	$METAR = Extrait_METAR($codeMETAR, $urlMETAR, $regex, $fileResult, 1);
	}
// ------------------- VERSION 3 --------------------------
	elseif( $METAR == '') {
				// http://cunimb.net/decodemet.php?station=LFML
		$urlMETAR = "http://cunimb.net/decodemet.php?station=$codeMETAR";
		$regex = '@blue">([.\s\S\w]{0,})</TH></TR></TABLE>@';
		$METAR = Extrait_METAR($codeMETAR, $urlMETAR, $regex, $fileResult, 3);
	}
// ------------------- VERSION 4 --------------------------
	elseif( $METAR == '') {
				// https://aviationweather.gov/adds/metars?station_ids=LFML&std_trans=translated&chk_metars=on&hoursStr=most+recent+only&submitmet=Submit
		$regex = "<STRONG>($codeMETAR.*)</STRONG";
		$urlMETAR = "https://aviationweather.gov/adds/metars?station_ids=$codeMETAR&std_trans=translated&chk_metars=on&hoursStr=most+recent+only&submitmet=Submit";
		$METAR = Extrait_METAR($codeMETAR, $urlMETAR, $regex, $fileResult, 4);
	}

	if ($METAR == '') { return ''; }

	// -------------------------------------------------------------------------------------------------------------
	// Extraction Station
//	preg_match('@^([A-Z]{1}[A-Z0-9]{3})@', $METAR, $found);
//	$result['Station'] = $found[1];

	// -------------------------------------------------------------------------------------------------------------
	// Extraction Date/heure
	preg_match('@([0-9]{2})([0-9]{2})([0-9]{2})Z@', $METAR, $found);
			$day    = intval($found[1]);
			$hour   = intval($found[2]);
			$minute = intval($found[3]);

			$observed_time = mktime($hour, $minute, 0, date('n'), $day, date('Y'));
			$HeureLocale = $observed_time + date('Z');
			$result['Jour'] = $day;
			$result['Heure_UTC'] = $found[2].':'.$found[3];
			$result['Heure_Locale'] = date('H:i', $HeureLocale);

	// -------------------------------------------------------------------------------------------------------------
	// Extraction Température
	preg_match('@(M?[0-9]{2})/(M?[0-9]{2}|[X]{2})@', $METAR, $found);
			$temperature_c = intval(strtr($found[1], 'M', '-'));
			$result['Temperature'] = $temperature_c;

			// Point de rosée
			if (isset($found[2]) AND strlen($found[2]) != 0 AND $found[2] != 'XX')
			{
				$dew_point_c = intval(strtr($found[2], 'M', '-'));
				$rh = round(100 * pow((112 - (0.1 * $temperature_c) + $dew_point_c) / (112 + (0.9 * $temperature_c)), 8));

				$result['Point_rosée'] = $dew_point_c;
				$result['Humidité'] = $rh;
			}

	// -------------------------------------------------------------------------------------------------------------
	// Extraction Vent
	preg_match('@([0-9]{3}|VRB|///)P?([/0-9]{2,3}|//)(GP?([0-9]{2,3}))?(KT|MPS|KPH)@', $METAR, $found);

	if ($found[1] == '///' AND $found[2] == '//')
		{
		} // gére le cas où rien n'est observé
		else
		{
		$result['Vent_Vitesse'] = round(1.852 * $found[2], 2);
		$result['Vent_Rafales'] = $result['Vent_Vitesse']; // Valeur par defaut
		$result['Vent_Direction'] = 180; // Valeur par defaut

		// Pas de vent si VRB
		if ($found[1] == 'VRB')
			{
			$result['Vent_Variable'] = TRUE;
			}
		// Sinon calcul direction, cap et rafales
		else {
			$direction = intval($found[1]);
			if ($direction == 0) { $direction = 180; } // par defaut
			if ($direction >= 0 AND $direction <= 360)
				{
				$result['Vent_Direction'] = $direction;
				$result['Vent_Direction_Label'] = mg::TranspoCap($direction);
				}

			// Rafales
			if (isset($found[4]) AND !empty($found[4]))
				{ $result['Vent_Rafales'] = round(1.852 * $found[4], 2); }
			}
	}
	// -------------------------------------------------------------------------------------------------------------
	// Extraction Pression
	preg_match('@^.*(Q|A)(////|[0-9]{4}).*$@', $METAR, $found);
	$pressure = intval($found[2]);
	if ($found[1] == 'A') { $pressure /= 100; }
	$result['Pression'] = $pressure;
	// -------------------------------------------------------------------------------------------------------------

	return $result;
}

/**********************************************************************************************************************
													Fabrique le message Météo locale
**********************************************************************************************************************/
function MsgMeteoLocale($EquipMeteo, $equipMeteoFrance) {
	$message = '';
	
	// Temps actuel
	$VitesseVent = round(mg::getCmd($EquipMeteo, 'Vitesse Vent METAR'));
	$Rafales = round(mg::getCmd($EquipMeteo, 'Vitesse Rafales METAR'));
	mg::TranspoCap(mg::getCmd($EquipMeteo, 'Direction Vent METAR'), $CapLibelle);
	$Temperature = round(mg::getCmd($EquipMeteo, 'Température METAR'));
	$humidité = round(mg::getCmd($EquipMeteo, 'Humidité METAR'));

	// Météo prévisionnelle
	$periode = 'Météo du Jour';
	$jour = 'Aujourdhui';

/*	$periode = 'Météo du Matin';
	if	(mg::getTag('#heure#') <= 14) { $periode = 'Météo du Midi'; }
	elseif	(mg::getTag('#heure#') < 19) { $periode = 'Météo du Soir'; } 

	// Si anomalie sur prévison période selon l'heure on prend celle de la journée
	if (!mg::existCmd($equipMeteoFrance, "$periode - $jour - Description")) {
		$periode = 'Météo du Jour';
	}*/

	// Lecture de la prévision Météo France de la période si existante
	if (mg::existCmd($equipMeteoFrance, "$periode - $jour - Description")) {
		$description = mg::getCmd($equipMeteoFrance, "$periode - $jour - Description");
		$vitesse_du_Vent = mg::getCmd($equipMeteoFrance, "$periode - $jour - Vitesse du Vent");
		$direction_du_Vent = mg::getCmd($equipMeteoFrance, "$periode - $jour - Direction du Vent");
		$force_Rafales = mg::getCmd($equipMeteoFrance, "$periode - $jour - Force Rafales");
		$température_Maximum = mg::getCmd($equipMeteoFrance, "$periode - $jour - Température Maximum");
		$température_Minimum = mg::getCmd($equipMeteoFrance, "$periode - $jour - Température Minimum");
		$indice_UV = mg::getCmd($equipMeteoFrance, "Météo du Jour - Aujourdhui - Indice UV");
		mg::setInf($EquipMeteo, 'Lib_MétéoFrance', $description);

		if (mg::getCmd($equipMeteoFrance, "Bulletin France - Texte de la période 1") != 0) {
			$message .= "Météo générale pour ".mg::getCmd($equipMeteoFrance, "Bulletin France - Nom de la période 1");
			$message .= "(...)\n(...)".mg::getCmd($equipMeteoFrance, "Bulletin France - Texte de la période 1");
		}
		
		// Sinon on prend les données REELLES
	} else {
		$periode = 'Météo constatée';
		$description = 'du moment';
		$vitesse_du_Vent = mg::getCmd($EquipMeteo, "Vitesse Réelle");
		$direction_du_Vent = mg::getCmd($EquipMeteo, "Direction Réelle");
		$force_Rafales = mg::getCmd($EquipMeteo, "Rafales Réelles");
		$température_Maximum = mg::getCmd($EquipMeteo, "Température Extérieur");
		$température_Minimum = mg::getCmd($EquipMeteo, "Température Extérieur");
		$indice_UV = 'inconnu';
		mg::setInf($EquipMeteo, 'Lib_MétéoFrance', '*** HS ***');
	}

	// MàJ des virtuels de météo
	mg::setInf($EquipMeteo, 'Heure Prévision Météo', $periode);
	mg::setInf($EquipMeteo, 'Vitesse Météo', $vitesse_du_Vent);
	mg::setInf($EquipMeteo, 'Direction Météo', $direction_du_Vent);
	mg::setInf($EquipMeteo, 'Rafales Météo', $force_Rafales);
	mg::setInf($EquipMeteo, 'Température Météo', $température_Maximum);
	mg::setInf($EquipMeteo, 'UV Météo', $indice_UV);

	// Construction du TTS des prévisions
	mg::TranspoCap($direction_du_Vent, $direction_du_Vent_Libelle);
	$température_Minimum = str_replace('.', ' virgule ', $température_Minimum);
	$température_Maximum = str_replace('.', ' virgule ', $température_Maximum);
	
	$message .= "(...)\n(...) Prévisions détaillées pour la $periode (...) : $description, UV $indice_UV, Température : $température_Minimum à $température_Maximum degrés, (Le vent souflera à $vitesse_du_Vent kilomètres heure du $direction_du_Vent_Libelle avec des rafales à $force_Rafales kilomètres heure.)";
	mg::message('', $message);

	return $message;
}

?>
