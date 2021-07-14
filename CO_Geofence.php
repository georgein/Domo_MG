<?php
/**********************************************************************************************************************
Geofence - 123
Signale l'approche d'un user
**********************************************************************************************************************/
global $IP_Jeedom, $API_Jeedom;

// Infos, Commandes et Equipements :
//	$equipPresence, $equipMapPresence

// N° des scénarios :

//Variables :
	$tabUser = mg::getVar('tabUser');
	$tabGeofence = mg::getVar('tabGeofence');

	$latLng_Home = '43.35071300,5.20839500,190';
	$homeSSID = ' Livebox-MG';					// Valeur contenue dans le SSID de 'HOME'

	$destinatairesSOS = "Log, SMS:@MG, Mail:MG";// Destinataires du SOS
	$timingSOS = 30;						// Durée max de pause 'normale' EN MINUTE en Entrainement au delà de laquelle on envoi un SOS

	$destinataires = 'Log, TTS:GOOGLECAST';	// Destinataire du message d'annonce de proximité

	$refreshCalcul = 60;					// Période de rafraichissement du recalcul de la carte
	$refresh = 5;							// Période de contrôle de rafraichissement de la page HTML en seconde
	$marqueurSize = 20;						// Taille des icones de marquage trajet
	$layerDefaut = 'GeoportailFrance_plan';	// Nom du layer par defaut OpenTopoMap, GeoportailFrance_maps, OpenStreetMap_France
	$epaisseur = 6;							// Epaisseur de la trace
	$colorNR = 'yellow';
	$colorMG = 'red';
	$colorPause = 'yellow';
	$colorVoiture = 'green';
	$colorEntrainement = 'red';
	$pauseMin = 2;							// Durée minimum de la Pause en mn pour l'afficher
	$coeffDist = 0.9;						// Annonce de proximité faite si DistCouranteUser * $CoeffDist < OldDistUser

	$coeffDistTotale = 0.9862; //1.0246;0.9580;				// Coeff à appliquer à la distance totale pour les Entrainements
	$coeffDeniveles = 1.0;					// Coeff à appliquer aux dénivelés
	$vitesseAltitudeMax = 0.22; //0.20;0.22;				// Vitesse maximale (en m/sec) pour prise en compte dans le nivellé de la valeur

	$pathRef = mg::getParam('System', 'pathRef');	// Répertoire de référence de domoMG
	$fileExport = (getRootPath() . "$pathRef/util/geofence.html");

// Paramètres :
	$IP_Jeedom = mg::getTag('#IP#');
	$API_Jeedom = mg::getConfigJeedom('core');
	$logTimeLine = mg::getParam('Log', 'timeLine');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
//$dateSQL_Test = '2021-05-09'; // ***** POUR DEBUG *****

$values = array();
$result = array();

$dateSQL = date('Y\-m\-d', time()); // Date du jour au format SQL
$userAppel = 'NR'; // Par defaut

// Gestion du déclencheur avec enregistrement éventuel en BdD
if (mg::declencheur('Position') || mg::declencheur('SSID')) {
	// CALCUL USER APPEL

	// Si JeedomConnect
	if (mg::declencheur('Position')) {
		$userAppel = str_replace('Tel-', '', mg::declencheur('', 2));
	}
	// Si SSID
	elseif (mg::declencheur('SSID')) {
		$userAppel = str_replace('variable(', '', mg::declencheur('', 2));
		$userAppel = str_replace('_SSID)', '', $userAppel);
	}

	$PositionJeedomConnect = mg::getCmd("#[Sys_Comm][Tel-$userAppel][Position]#", '', $collectDate, $valueDate);
	$ActiviteJeedomConnect = mg::getCmd("#[Sys_Comm][Tel-$userAppel][Activité]#");
	$GeofencepcBat = mg::getCmd("#[Sys_Comm][Tel-$userAppel][Batterie]#");

	$GeofenceSSID = mg::getVar($userAppel.'_SSID', 'Pas de SSID');
	
	
	if (mg::declencheur('SSID')) {
		$valueDate = time();
	//  SI at HOME
		if( strpos(" $GeofenceSSID", $homeSSID) !== false) {
			$PositionJeedomConnect = $latLng_Home;
			mg::setVar("dist_Tel-$userAppel", -1);
			mg::unsetVar("_OldDist_$userAppel");
			$ActiviteJeedomConnect = 'I_HOME';
		}
		mg::message('',"Déclencheur SSID ==> $GeofenceSSID - $homeSSID - $ActiviteJeedomConnect");
	}

	// Mise en forme de l'Activité
	if ($ActiviteJeedomConnect == 'still') { $ActiviteJeedomConnect = "I_$ActiviteJeedomConnect"; }
	elseif ($ActiviteJeedomConnect == 'walking') { $ActiviteJeedomConnect = "E_$ActiviteJeedomConnect"; }
	elseif ($ActiviteJeedomConnect == 'on_foot') { $ActiviteJeedomConnect = "E_$ActiviteJeedomConnect"; }
	elseif ($ActiviteJeedomConnect == 'running') { $ActiviteJeedomConnect = "E_$ActiviteJeedomConnect"; }
	elseif ($ActiviteJeedomConnect == 'on_bicycle') { $ActiviteJeedomConnect = "E_$ActiviteJeedomConnect"; }
	elseif ($ActiviteJeedomConnect == 'in_vehicle') { $ActiviteJeedomConnect = "V_$ActiviteJeedomConnect"; }

	// ENREGISTREMENT DU NOUVEAU POINT
	$newValue = "$PositionJeedomConnect,$GeofencepcBat,$GeofenceSSID,$ActiviteJeedomConnect";
	$idUserAppel = trim(mg::toID($equipMapPresence, $userAppel), '#');
	$valueDateTxt = date('Y-m-d H:i:s', $valueDate);

	// Marquage doublon position courante
	if (mg::declencheur('Position') && $GeofenceSSID == 'Pas de SSID') {
		$DateTxt = date('Y-m-d 00:00:00', time());
		$latlngs = explode(',', $newValue);
		$latlngs = $latlngs[0].','.$latlngs[1];
		$newValueDoublon = $newValue.'_XX';
		$sql = "UPDATE `history` SET `value`= '$newValueDoublon' WHERE `cmd_id` = '$idUserAppel' AND `value` REGEXP '$latlngs' and `datetime` < '$valueDateTxt' and `datetime` > '$DateTxt'";
		$result = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
	}

	if (count($result, COUNT_RECURSIVE) + count($values, COUNT_RECURSIVE) > 0) mg::message('Log:/_Tmp', $valueDateTxt .' - ' . count($values, COUNT_RECURSIVE).' - '.count($result, COUNT_RECURSIVE));
	// On enregistre position courante si pas de doublon
	if (count($result) == 0) {
		$sql = "INSERT INTO `history` (cmd_id, datetime, value)
				VALUES ('$idUserAppel', '$valueDateTxt', '$newValue')
				ON DUPLICATE KEY UPDATE datetime='$valueDateTxt'";
		mg::messageT('', "! User : $userAppel => Enregistrement d'un point au $valueDateTxt $GeofenceSSID");
		$result = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
	}
}

// On sort si data successive rapide (provenant du buffer de JeedomConnect)
$lastCalcul = mg::getVar("_GeoLastRun_$userAppel");
if ((time() - $lastCalcul) < $refreshCalcul && mg::declencheur('Position')) { return; }
mg::setVar("_GeoLastRun_$userAppel", time());

/*
select t1.cmd_id, t1.datetime, t1.value, t2.value, t3.value

from history t1

-- inner join history t1 on t1.cmd_id = 24579
left join history t2 on t2.cmd_id = 24843 and t2.datetime = t1.datetime
left join history t3 on t3.cmd_id = 10996 and t3.datetime = t1.datetime

where t1.cmd_id = 24579 && t1.datetime > '2021-06-13 00:00:00'

ORDER BY `datetime` DESC LIMIT 500
*/

// *********************************************** TRAITEMENT USER APPEL **********************************************
	$idUser = mg::toID($equipMapPresence, $userAppel);
	$userPresent = mg::getCmd($equipPresence, $userAppel);

	if (!$userPresent) { $tabGeofence[$userAppel]['dateActive'] = $dateSQL; }
	elseif (isset($tabGeofence[$userAppel]['dateActive'])) { $dateSQL = @$tabGeofence[$userAppel]['dateActive']; }
	if (isset($dateSQL_Test)) { $dateSQL = $dateSQL_Test; }

	// Lancement du calcul de la carte Geofence du user
		makeLatLng($tabGeofence, $userAppel, $lastLatLng_User, $dateSQL, $idUser, $pauseMin, $coeffDeniveles, $destinatairesSOS, $timingSOS, $SSID, $latLng, $nbLignes, $coeffDistTotale, $vitesseAltitudeMax);

	mg::messageT('', "! User : $userAppel / Présent : $userPresent - NbLignes : $nbLignes - debTime : " . date('d\/m \à H\hi\m\n', $tabGeofence[$userAppel]['debTime']) . " - Cloture : " . date('d\/m \à H\hi\m\n', $tabGeofence[$userAppel]['cloture'])." - DateSQL : $dateSQL");
	if ($nbLignes < 3) { return; }

	$latLng_ = explode(',', $latLng);
	$latLng_Home_ = explode(',', $latLng_Home);
	$dist = mg::getDistance($latLng_Home_[0], $latLng_Home_[1], $latLng_[0], $latLng_[1], 'k');

	// ******************************************** MAJ WIDGET GOOGLE MAP *****************************************
	$tmp = mg::getParamWidget($idUser, '');
	$tmp['from'] = $latLng_[0] . ', ' . $latLng_[1];
	mg::setParamWidget($idUser, '', $tmp);
	$tmp['to'] = $latLng_Home_[0] . ', ' . $latLng_Home_[1];
	mg::setParamWidget($idUser, '', $tmp);

	// **************************************** SIGNALEMENT PROXIMITE *********************************************
	mg::messageT('', "! Signalement de proximité de $userAppel à $dist km");
	mg::setVar("dist_Tel-$userAppel", $dist);
	$distanceMax = floatval($tabUser["Tel-$userAppel"]['geo']);
	$oldDist = mg::getVar("_OldDist_$userAppel", $dist);
	if (abs($dist-$oldDist) > $distanceMax && $dist < $oldDist * $coeffDist && $dist > $distanceMax) {
		$distTxt = str_replace('.', ',', round($dist,1));
		mg::Message($destinataires, "'$userAppel' en approche à $distTxt kilomètres.");
		mg::setVar("_OldDist_$userAppel", $dist);
	} else {
		mg::setVar("_OldDist_$userAppel", max($dist, $oldDist));
	}

//	******************************************* ECRITURE DU FICHIER HTML **********************************************
mg::messageT('', "! GENERATION HTML GEOFENCE (new $userAppel)");
file_put_contents($fileExport, HTML($tabGeofence, $userAppel, $latLng_Home, $lastLatLng_User, $refresh, $layerDefaut, $marqueurSize, $pauseMin, $epaisseur, $colorMG, $colorNR, $colorPause, $colorEntrainement, $colorVoiture, $pathRef));
mg::setVar('tabGeofence', $tabGeofence);
mg::setVar('_geofenceOK', time());
mg::message('', print_r($tabGeofence, true));

// ********************************************************************************************************************
// ********************************************************************************************************************
// ********************************************************************************************************************
function HTML($tabGeofence, $userAppel, $latLng_Home, $lastLatLng_User, $refresh, $layerDefaut, $marqueurSize, $pauseMin, $epaisseur, $colorMG, $colorNR, $colorPause, $colorEntrainement, $colorVoiture, $pathRef) {
global $IP_Jeedom, $API_Jeedom;

$tailleBoutons = '96px';

// Page HTML
$geofence = "
<!DOCTYPE html>
<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"fr\">
	<head>
		<meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\" />
		<link rel=\"stylesheet\" href=\"$pathRef/ressources/leaflet/leaflet.css\" />
		<script src=\"$pathRef/ressources/leaflet/leaflet.js\"></script>
		<link rel=\"stylesheet\" href=\"$pathRef/ressources/leaflet/easy-button.css\" />
		<script src=\"$pathRef/ressources/leaflet/easy-button.js\"></script>

		<script src='https://code.jquery.com/jquery-3.4.1.min.js'></script>
	</head>

	<style>
		html, body {
			height: 100%
	}

	.leaflet-marker-icon.leaflet-interactive {
		border-radius: 50% /* Mise cercle des icones */
	}

	.leaflet-bar button, .leaflet-bar button:hover, .leaflet-bar a, .leaflet-bar a:hover, .leaflet-control-layers-toggle {
		width: $tailleBoutons!important; /* Largeur bouton image de zoom */
		height: $tailleBoutons!important; /* Hauteur bouton image de zoom */
		line-height: $tailleBoutons;
	}

	.leaflet-control-zoom-in, .leaflet-control-zoom-out {
		font: bold $tailleBoutons 'Lucida Console', Monaco, monospace;
	}

	.leaflet-bar, .leaflet-bar button:first-of-type, .leaflet-bar button:last-of-type, .leaflet-bar a:first-child, .leaflet-bar a:last-child {
	  border-radius: 50%;
	}

	.leaflet-bar {
		box-shadow: 0 15px 15px rgba(0,0,0,0.65);
	}

	/* general typography */
	.leaflet-container {
	font: 30px/2.5 'Helvetica Neue', Arial, Helvetica, sans-serif!important;
	}

.leaflet-bar, .leaflet-bar button:first-of-type, .leaflet-bar button:last-of-type, .leaflet-bar a:first-child, .leaflet-bar a:last-child {
    border-top-left-radius: 50%!important;
    border-top-right-radius: 50%!important;
    border-bottom-right-radius: 50%!important;
    border-bottom-left-radius: 50%!important;
}
.leaflet-touch .leaflet-bar a {
    width: $tailleBoutons!important;
    height: $tailleBoutons!important;
    line-height: $tailleBoutons!important;
}

	.leaflet-touch .leaflet-control-zoom-out {
    font-size: $tailleBoutons!important;
	color:darkred;
}
	.leaflet-touch .leaflet-control-zoom-in {
    font-size: $tailleBoutons!important;
	color:darkgreen;
}

	.leaflet-control-scale-line {
		border: 2px solid #777;
		border-top: none;
		line-height: 1.1;
		padding: 5px 5px 5px;
		margin-bottom:10px;
		font-size: 25px;
		color:white;
		white-space: nowrap;
		overflow: hidden;
		-moz-box-sizing: border-box;
		box-sizing: border-box;
		background: darkred;
	}

	.leaflet-popup-content {
		margin: 5px 10px;
		line-height: 1.4!important;
		font-size: 22px!important;
		width:600px!important;
		}

	.leaflet-tooltip { /* tooltip des icones user */
		font-size: 22px;
		line-height: 1.4!important;
	}

	.bt-reload {
		position: relative;
		text-align: center;
		background-color: red!important;
		color: white;
		margin-left: 50%;
		margin-top: 20px;

	}

</style>

		<button class='bt-reload' value='Reload' id='reload' onclick='reload();' title='Recharge la page.'>--- Reload ---</button>
	<body onload=\"initialize()\">
		<div id=\"map\" style=\"width:100%; height:100%\"></div>
	</body>
</html>

<script type=\"text/javascript\">
// ********************************************************************************************************************

function getCookie(sName) {
	var oRegex = new RegExp('(?:; )?' + sName + '=([^;]*);?');
	if (oRegex.test(document.cookie)) {
			return decodeURIComponent(RegExp['$1']);
	} else {
			return null;
	}
}

function setCookie(sName, sValue) {
	var today = new Date(), expires = new Date();
	expires.setTime(today.getTime() + (365*24*60*60*1000));
	document.cookie = sName + '=' + encodeURIComponent(sValue) + ';expires=' + expires.toGMTString();
}

	// Recharge la page au changement de valeur d'une variable Jeedom
	function loadRefresh(varName, refresh=5, newValue='') {
		IP = 'http://$IP_Jeedom';
		apiJeedom = '$API_Jeedom';
		requete = IP+'/core/api/jeeApi.php?apikey='+apiJeedom+'&type=variable&name='+varName+ (newValue != '' ? '&value=\"'+newValue+'\"' : '');
		var old_geofenceOK = getCookie('_geofenceOK');
//		alert('DEBUT => '+old_geofenceOK);

		$.get(requete, function( data, status ) {
			$('#result').html( data );
			if ((data-old_geofenceOK) > refresh) {
				setCookie('_geofenceOK', data);
		//debugger;
//			alert('RELOAD '+data+' - '+old_geofenceOK+' => '+(data-old_geofenceOK));
				reload();
			}
		setTimeout('loadRefresh(\"_geofenceOK\")',refresh*1000);
		});
	}

// ----------------------------------------------------- API JEEDOM ---------------------------------------------------
	// Lit une commande info de Jeedom
	function getCmd(id) {
		IP = 'http://$IP_Jeedom';
		apiJeedom = '$API_Jeedom';
		requete = '/core/api/jeeApi.php?apikey='+apiJeedom+'&type=cmd&id='+id;
		$.get(requete, function( data, status ) {
			$('#result').html( data );
		});
		return data;
	}

// ********************************************************************************************************************

function reload() { document.location.reload(); }

loadRefresh('_geofenceOK', $refresh);

function initialize() {


	var latLng_Home= L.latLng($latLng_Home);
	var lastLatLng_User = L.latLng($lastLatLng_User);

	var map = L.map('map').setView(lastLatLng_User, (getCookie('zoom') > 0 ? getCookie('zoom') : 14));

	L.control.scale({position: 'bottomright', maxWidth:500, imperial:false}).addTo(map); // Pose de l'échelle

	// ******************** DOC SYNTAXE LEAFLET : https://leafletjs.com/reference-1.6.0.html#path *********************

// ********************************************* MARQUEUR POINT DE TRACE **********************************************
	var iconEntrainement = new L.Icon({
	  iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-$colorEntrainement.png',
	  iconSize: [$marqueurSize, $marqueurSize],
	  iconAnchor: [$marqueurSize/2, $marqueurSize/2],
	  popupAnchor: [1, -1.2*$marqueurSize],
	});

	var iconVoiture = new L.Icon({
	  iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-$colorVoiture.png',
	  iconSize: [$marqueurSize, $marqueurSize],
	  iconAnchor: [$marqueurSize/2, $marqueurSize/2],
	  popupAnchor: [1, -1.2*$marqueurSize],
	});

	var iconPause = new L.Icon({
	  iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-$colorPause.png',
	  iconSize: [$marqueurSize, $marqueurSize],
	  iconAnchor: [$marqueurSize/2, $marqueurSize/2],
	  popupAnchor: [1, -1.2*$marqueurSize],
	});

	var iconPauseLongue = new L.Icon({
	  iconUrl: \"$pathRef/img/img_Binaire/presences/sleepingEmoji.png\",
	  iconSize: [$marqueurSize*2, $marqueurSize*2],
	  iconAnchor: [$marqueurSize/2, $marqueurSize/2],
//	  shadowSize: [41, 41]
	  popupAnchor: [1, -1.2*$marqueurSize],
	});

";

$polylineAll = '';
$numUser = 0;
foreach ($tabGeofence as $user => $detailsUser) {

	$latlngs = mg::getVar("tabGeofence_L_$user");
	if (!$latlngs) continue;

	$complements = mg::getVar("tabGeofence_C_$user");

	$debTime = intval($tabGeofence[$user]['debTime']);
	$lastTime = intval($tabGeofence[$user]['lastTime']);
	$lastPcBatterie = intval($tabGeofence[$user]['lastPcBatterie']);
	$lastSSID = $tabGeofence[$user]['lastSSID'];

	$distanceVoiture = round($tabGeofence[$user]['distanceVoiture'], 1);
	$distanceTotale = round($tabGeofence[$user]['distanceTotale'], 1);
	$denivelePlus = round($tabGeofence[$user]['denivelePlus'], 0);
	$deniveleMoins = round($tabGeofence[$user]['deniveleMoins'], 0);
	$cloture = intval(($tabGeofence[$user]['cloture'] == 0 ? $tabGeofence[$user]['lastTime'] : $tabGeofence[$user]['cloture']));
	$vitesseMoyenneTotale = round($tabGeofence[$user]['vitesseMoyenneTotale'], 1);
	$vitesseMoyenneMouvement = round($tabGeofence[$user]['vitesseMoyenneMouvement'], 1);

	$debTimeStr = date('d/m \&#224;\ H:i', $debTime);
	$lastTimeStr = date('d/m \&#224;\ H:i', $lastTime);
	$clotureStr = date('d/m \&#224;\ H:i', $cloture);
	$dureeTotaleStr = gmdate('H\h\ i', intval($tabGeofence[$user]['dureeTotale']));
	$dureeMouvementStr = gmdate('H\h\ i', intval($tabGeofence[$user]['dureeMouvement']));
	$sommePauseStr = gmdate('H\h\ i\ \m\n', intval($tabGeofence[$user]['sommePause']*60));

	$polylineAll .= "polyline$user,";
	$imgBouton = "$user.png";
	$colorUser = $user == 'MG' ? $colorMG : $colorNR;
	$numUser++;

// ********************************************************************************************************************
// ********************************************************* DEBUT USER ***********************************************
// ********************************************************************************************************************
$geofence .=
	"
	// *********************************************** DEBUT DE LA TRACE $user ****************************************

	// ************************************ EVENEMENTS POUR MISE A JOUR DES COOKIES ***********************************
	// Intercepte les changements de zoom et MàJ du cookie
	var prevZoom = map.getZoom();
	map.on('zoomend',function(e){
//		debugger;
		var currZoom = map.getZoom();
		if(currZoom != prevZoom){
			setCookie('zoom', currZoom);
		}
		prevZoom = currZoom;
	});

	// Intercepte les changements de layer
	map.on('baselayerchange', function (e) {
	setCookie('layer', e.layer.getAttribution());
	});
//////////////////////////////////////////////////////////////////////////////////////////////////////////////

	var latlngs = [$latlngs];
	var complements = [$complements];
	var dureePause = 0;
	var polyline$user = L.polyline(latlngs, {color:'$colorUser', weight:'$epaisseur' }).addTo(map);

	var iconUser = new L.icon({
		iconUrl: \"$pathRef/img/img_Binaire/presences/$user.png\",
		shadowUrl:	  'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
		iconSize:	  [48, 48], // taille de l'icone
		iconAnchor:	  [24, 48*$numUser-48], // point de l'icone qui correspondra à la position du marker
		shadowSize:	  [96, 96], // taille de l'ombre
		shadowAnchor: [48, 96], // idem pour l'ombre
		popupAnchor:  [-3, 42 -48*$numUser] // point depuis lequel la popup doit s'ouvrir relativement à l'iconAnchor
	});
	
	var dateStringPause = '';

	// ********************************************** PARCOURS DE LA TRACE ********************************************
	for ( var i=1; i < latlngs.length-1; ++i ) {
		var time = latlngs[i][2]*1000;
		// Conversion date en string
		var date = new Date(time);
		annee = date.getFullYear();
		moi = date.getMonth();
	   mois = new Array('Janvier', 'F&eacute;vrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Ao&ucirc;t', 'Septembre', 'Octobre', 'Novembre', 'D&eacute;cembre');
		j = date.getDate();
		jour = date.getDay();
		jours = new Array('Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi');
		h = date.getHours();
		if(h<10) { h = '0'+h; }
		m = date.getMinutes();
		if(m<10) { m = '0'+m; }
		s = date.getSeconds();
		if(s<10) { s = '0'+s; }
		dateString = j+' '+mois[moi] +' '+annee + ' &#224; ' + h + ':' + m + ':' + s;
		// Fin de la conversion

		var coordonnee = latlngs[latlngs.length-1].toString();
		var	 latLng = coordonnee.split(',');
		latLng_ = L.latLng(latlngs[latlngs.length-1]);
		var latitude = latLng[0];
		var longitude = latLng[1];

		var ecart = parseFloat(complements[i][0]);
		var altitude = parseInt(complements[i][2]);
		var activite = complements[i][3];
		var pcBatterie = parseInt(complements[i][4]);
		var dureePause = parseInt(complements[i][5]);
		var deltaI_Pause = parseInt(complements[i-1][6]);

		// Marqueur VOITURE
		if (activite == 'V') {
			L.marker( latlngs[i], {icon: iconVoiture} ).bindTooltip('$user : '	+ dateString + ' - Bat. ' + pcBatterie + ' %').addTo( map );
		}

		// Marqueur ENTRAINEMENT
		if (activite == 'E' || (activite == 'I' && $debTime > 0)) {
			L.marker( latlngs[i], {icon: iconEntrainement} ).bindTooltip( '<center> $user : ' + dateString + ' - '
				+ ecart + ' km - Alt ' + altitude + ' m - Bat. ' + pcBatterie + ' %'
				+ '<br>Coordonn&#232;es : (' + latitude + ' , ' + longitude + ')').addTo( map )
		}

		if (activite == 'I') {
			if (deltaI_Pause == 0) { dateStringPause = dateString; } else { dateStringPause = dateStringPause; }
			// Marqueur de PAUSE LONGUE, en fin de pause on affiche l'icone
			if (dureePause > $pauseMin) {
						L.marker( latlngs[i-deltaI_Pause], {icon: iconPauseLongue} ).bindTooltip('$user : ' + dateStringPause + ' - PAUSE de ' + Math.round(dureePause) + ' mn - Alt ' + altitude + ' m').addTo( map );
			}

			// Marqueur de PAUSE PONCTUELLE
			else {
				L.marker( latlngs[i], {icon: iconPause} ).bindTooltip('$user : ' + dateString + ' - PAUSE de ' + Math.round(dureePause) + ' mn - Alt ' + altitude + ' m').addTo( map );
			}
		}
		
	} // Fin parcours trace
	
	// Icone du User sur la carte
	var message = '';
		if ($debTime != 0) {
		message = ' <center>********************* ENTRAINEMENT $user *********************'
		+ '<br>du $debTimeStr au $clotureStr'
		+ '<br>Dur&eacute;e $dureeTotaleStr, en mouvement $dureeMouvementStr, ($sommePauseStr de pause)'
		+ '<br>Dist ' + Math.round($distanceTotale*100)/100 + ' km - d&eacute;nivel&eacute;s +' + $denivelePlus + ' m / ' + $deniveleMoins + ' m - Vitesse		$vitesseMoyenneTotale / $vitesseMoyenneMouvement km/h'
		+ '<br> -------------------------------------------------------------------------------<br>';
		}
		message =	message + '<center>$user : $lastTimeStr : Batterie $lastPcBatterie % - $lastSSID'
					+ '<br>Kilom&egrave;trage voiture du jour : $distanceVoiture km.'
					+'<br> Coordonn&#232;es : (' + latitude + ' , ' + longitude + ')';

	L.marker(latLng_, {icon: iconUser}).addTo(map).bindTooltip( message ).openPopup();

	// ******************************************** BOUTONS de zoom $user *********************************************
		L . easyButton ( '<img src = \"$pathRef/img/img_Binaire/presences/$imgBouton\">' ,  function ( btn , map ) {
			var	 $user	=  L.latLng(latlngs[latlngs.length-1]);
			var group = new L.featureGroup([polyline$user]);
			map.fitBounds(group.getBounds());
		} ) . addTo ( map ) ;

	// ********************************************* FIN DE LA TRACE $user ********************************************
	";
}

// ********************************************************************************************************************
// ********************************************************** FIN USER ************************************************
// ********************************************************************************************************************
$geofence .= "

	// ********************************************* BOUTONS de zoom All **********************************************
	L . easyButton ( '<img src = \"$pathRef/img/img_Binaire/presences/monde.png\">' , function ( btn , map ) {
	var group = new L.featureGroup([$polylineAll]);
	map.fitBounds(group.getBounds());
} ) . addTo ( map ) ;

	// ************************************** ZOOM AU CHARGEMENT SUR UserActif ***************************************
	var group = new L.featureGroup([polyline$userAppel]);
if ('$userAppel' == '$user') {map.fitBounds(group.getBounds());}
	////////////////////////////////////////////////////////////////////////////////////////////

	// *************************************** POSE DU REPERE HOME (REGION RONDE) *************************************
	var influence = L.circle(latLng_Home, 100, {
		'color': 'red',
		'fill': true,
		'fillColor': 'red',
		'fillOpacity': 0.2,
	}).addTo(map);

	// ****************************************************************************************************************
	// ****************************************************************************************************************
	// ***************** LISTE DES LAYERS SUR : http://leaflet-extras.github.io/leaflet-providers/preview/ ****************
	// ***************** SITE DE LEAFLET : https://leafletjs.com/ *****************

	// **************************************** DEFINITIONS DES LAYERS PROPOSES ***************************************

	var OpenStreetMap_France = L.tileLayer('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', {
		maxZoom: 20,
		attribution: 'OpenStreetMap_France'
	});

	var GeoportailFrance_plan =
	L.tileLayer('https://wxs.ign.fr/{apikey}/geoportail/wmts?REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE={style}&TILEMATRIXSET=PM&FORMAT={format}&LAYER=GEOGRAPHICALGRIDSYSTEMS.PLANIGNV2&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}', {
		attribution: 'GeoportailFrance_plan',
		bounds: [[-75, -180], [81, 180]],
		minZoom: 2,
		maxZoom: 19,
		apikey: 'choisirgeoportail',
		format: 'image/png',
		style: 'normal'
	});

	var GeoportailFrance_orthos = L.tileLayer('https://wxs.ign.fr/{apikey}/geoportail/wmts?REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE={style}&TILEMATRIXSET=PM&FORMAT={format}&LAYER=ORTHOIMAGERY.ORTHOPHOTOS&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}', {
		attribution: 'GeoportailFrance_orthos',
		bounds: [[-75, -180], [81, 180]],
		minZoom: 2,
		maxZoom: 19,
		apikey: 'choisirgeoportail',
		format: 'image/jpeg',
		style: 'normal'
	});
/********** FIN INIT DES LAYERS **********/

// ******************************************* AFFICHAGE DU CHOIX DES LAYERS ******************************************
	var layer = getCookie('layer') ? getCookie('layer') : $layerDefaut;
	map.addLayer(eval(layer)); // Le layer par défaut
	map.addControl(new L.Control.Layers( {
		'Plan IGN': GeoportailFrance_plan,
		'Satellite': GeoportailFrance_orthos,
		'Plan OSM': OpenStreetMap_France,

/*		'OpenStreetMap': osmLayer,
		'OpenTopoMap': OpenTopoMap,
		'Esri_WorldImagery': Esri_WorldImagery,
		'GeoportailFrance_ignMaps': GeoportailFrance_ignMaps,
		'GeoportailFrance_parcels': GeoportailFrance_parcels,
		'GeoportailFrance_maps': GeoportailFrance_maps,*/
		}, {})
	);
	// ************************************ FIN DE L'AFFICHAGE DU CHOIX DES LAYERS ************************************
}
</script>

";

return $geofence;
}

/*********************************************************************************************************************/
/************************************************* Extrait les LatLong de l'historiques ******************************/
/*********************************************************************************************************************/
function makeLatLng(&$tabGeofence, $user, &$lastLatLng_User, $dateSQL, $id, $pauseMin, $coeffDeniveles, $destinatairesSOS, $timingSOS, &$SSID, &$latlng, &$nbLignes, $coeffDistTotale, $vitesseAltitudeMax) {
	global $scenario;

	$values = array();
	$tabGeofence_L = '';
	$tabGeofence_C = '';

	$tabGeofence[$user]['distanceVoiture'] = 0;
	$tabGeofence[$user]['dureeMouvement'] = 0;
	$tabGeofence[$user]['vitesseMoyenneTotale'] = 0;
	$tabGeofence[$user]['vitesseMoyenneMouvement'] = 0;
	$tabGeofence[$user]['debTime'] = 0;
	$tabGeofence[$user]['cloture'] = 0;
	$tabGeofence[$user]['distanceTotale'] = 0;
	$tabGeofence[$user]['dureeTotale'] = 0;
	$tabGeofence[$user]['denivelePlus'] = 0;
	$tabGeofence[$user]['deniveleMoins'] = 0;
	$tabGeofence[$user]['sommePause'] = 0;
	$tabGeofence[$user]['SSID_Org'] = 'xxx';
	$tabGeofence[$user]['alerteEnCours'] = 0;
	
	$oldTimeCourante = 0;
	$oldActivite = '';
	$oldLatitude = 0;
	$oldLongitude = 0;
	$oldAltitude = 0;
	$azimut = 0;

	$timeCourante = 0;
	$dureePause = 0;
	$ecart = 0;
	$dureeEcart = 0;
	$altitude = 0;
	$ecartAltitude = 0;
	$debPause = 0;
	$deltaI_Pause = 0;
	$vitesseEcart = 0;
	$vitesseAltitude = 0;

	$id = trim($id, '#');
	$sql = "
		SELECT value, datetime
		FROM `history`
		WHERE `cmd_id` = '$id' AND `datetime` LIKE '%$dateSQL%'
		ORDER BY `datetime` ASC
		-- LIMIT 2000
	";
	mg::message('', $sql);
	$result = DB::Prepare($sql, (array)$values, DB::FETCH_TYPE_ALL);

	for($i=0; $i<count($result); $i++) {
		// ******************************************** Init des variables ********************************************
		$inhibe = 0;

		if ($i<count($result)-1) {
			$timeCourante_P1 = strtotime($result[$i+1]['datetime']);
			$latlng_P1 = explode(',', $result[$i+1]['value']); // Decomposition de value
			$activite_P1 = (strpos($latlng_P1[5], 'XX') !== false) ? 'X' : strtoupper(trim($latlng_P1[5])[0]);
		}

		$timeCourante = strtotime($result[$i]['datetime']);
		$latlng_ = explode(',',$result[$i]['value']); // Decomposition de value
		$latitude = $latlng_[0];
		$longitude = $latlng_[1] . ($i == 1 ? '1': ''); // Pour avoir une dif. minimum pour la carte
		$altitude = $latlng_[2];

		$activite = (strpos($latlng_[5], 'XX') !== false) ? 'X' : strtoupper(trim($latlng_[5])[0]);
		$latlng = $latitude.','.$longitude;
		$pcBatterie = intval($latlng_[3]);
		$SSID = trim(trim($latlng_[4]));

		if ($i > 0) {
			$ecart = mg::getDistance($oldLatitude, $oldLongitude, $latitude, $longitude, 'k', $azimut);
			$dureeEcart = $timeCourante - $oldTimeCourante;
			$vitesseEcart = round($ecart / $dureeEcart*3600, 1);

			$ecartAltitude = round($altitude - $oldAltitude);
			$vitesseAltitude = round(abs($ecartAltitude/$dureeEcart), 3); // en m/s

			// ****************************************** INHIBE POINTS EN ERREUR *****************************************
			if ($SSID != 'Pas de SSID') $activite = 'I';

			// ANOMALIES entrainement en cours
			if ($tabGeofence[$user]['debTime'] > 0 && $tabGeofence[$user]['cloture'] == 0) {
				if ($vitesseEcart > 2 && $vitesseEcart < 8) $activite = 'E';
				if ($vitesseEcart < 1 && $dureeEcart > $pauseMin*60) $activite = 'I';
				if ($vitesseEcart > 7) $inhibe = 1;
			}

			// ANOMALIES générales
			if (
					$activite == 'X' ||
					($activite == 'I' && $vitesseEcart > 2) ||
					($activite == 'E' && $vitesseEcart > 8) ||
					($activite == 'V' && $vitesseEcart > 130)
				)
			{ $inhibe = 1; }

			// ****************************************** ACTIVITE 'V'OITURE ******************************************
			if (!$inhibe && $activite == 'V') {
				$tabGeofence[$user]['distanceVoiture'] += $ecart;
			}

			// ******************************************** ACTIVITE 'E' **********************************************
			if ($activite == 'I'  || $activite == 'E') {
				// Demarrage Entrainement
				if ($SSID == 'Pas de SSID' && $activite == 'E' && $tabGeofence[$user]['debTime'] <= 0) {
				if ($SSID != 'Pas de SSID' && $tabGeofence[$user]['debTime'] <= 0) { $tabGeofence[$user]['SSID_Org'] = $SSID; }
					$tabGeofence[$user]['debTime'] = $timeCourante;
					$tabGeofence[$user]['cloture'] = 0;
					$tabGeofence[$user]['distanceTotale'] = 0;
//					$dureePause = 0;
					$tabGeofence[$user]['sommePause'] = 0;
					$tabGeofence[$user]['denivelePlus'] = 0;
					$tabGeofence[$user]['deniveleMoins'] = 0;
				}
					// Entrainement EN COURS
				if (!$inhibe && $tabGeofence[$user]['debTime'] > 0 && $tabGeofence[$user]['cloture'] == 0) {

					// HORS PAUSE
					if ($activite != 'I' && $oldActivite != 'I') {
						$tabGeofence[$user]['distanceTotale'] += $ecart * $coeffDistTotale;

						// Traitement altitude
						if ($vitesseAltitude < $vitesseAltitudeMax) {
							if ($ecartAltitude > 0) { $tabGeofence[$user]['denivelePlus'] += ($ecartAltitude * $coeffDeniveles); }
							if ($ecartAltitude < 0) { $tabGeofence[$user]['deniveleMoins'] += ($ecartAltitude * $coeffDeniveles); }
						}
					}

					// ********** GESTION DES PAUSES **********
					if ($activite == 'I') {
						// Début de pause
						if ($debPause == 0) {
							$debPause = $oldTimeCourante;
							$dureePause = round(($timeCourante_P1 - $debPause)/60, 1);
							$deltaI_Pause = 1;

						// Pause en cours
						} else {
							$dureePause = round(($timeCourante_P1 - $debPause)/60, 1);
							$deltaI_Pause++;
							
							// *************** ENVOIS D'UN SOS AUTOMATIQUE ***************
							if ($tabGeofence[$user]['cloture'] == 0 && $tabGeofence[$user]['distanceTotale'] > 1 && $dureePause > $timingSOS && $tabGeofence[$user]['alerteEnCours'] == 0 && abs(time() - $timeCourante) < 1*60) {
								mg::message($destinatairesSOS, "SOS AUTOMATIQUE de $user, Aucun mouvement depuis ".round($dureePause)." mn, Coordonnées ($latlng). VOIR :  https://georgein.dns2.jeedom.com/mg/util/geofence.html");
								$tabGeofence[$user]['alerteEnCours'] = $timeCourante;
							}
						}

					// Fin de pause
					} elseif ($debPause > 0) {
						$dureePause = round(($timeCourante_P1 - $debPause)/60, 1);
						$tabGeofence[$user]['sommePause'] += $dureePause;
						$debPause = 0;
						$dureePause = 0;
						$deltaI_Pause = 0;
						$tabGeofence[$user]['alerteEnCours'] = 0;
						
					} // ********** FIN GESTION DES PAUSES **********
				} // ********** FIN ENTRAINEMENT EN COURS **********
			} // ********** FIN ACTIVITE 'E' **********

			// ****************************************** CLOTURE ENTRAINEMENT ****************************************
			if ($tabGeofence[$user]['debTime'] > 0 && $tabGeofence[$user]['cloture'] == 0) {
				if (	$SSID != 'Pas de SSID'
						 || strpos($tabGeofence[$user]['SSID_Org'], $SSID) !== false
						 || strpos($SSID, $tabGeofence[$user]['SSID_Org']) !== false
						 || ($activite == 'V' && $activite_P1 == 'V' && $vitesseEcart > 10) )
					{
					$tabGeofence[$user]['cloture'] = $oldTimeCourante;
					$tabGeofence[$user]['alerteEnCours'] = 0;
				}
			}

			// ********** SUPPRESSION ENTRAINEMENT TROP COURT (< 1 km ou 15 mn) **********
			if ($tabGeofence[$user]['cloture'] > 0 && $timeCourante +300 > $tabGeofence[$user]['cloture'] && ($tabGeofence[$user]['distanceTotale'] < 1)) {
				mg::message('', "************* Suppression entrainement trop court de $user : Distance : " . $tabGeofence[$user]['distanceTotale'].' - Durée : '.date('H:m:s', $tabGeofence[$user]['dureeTotale']));
				$dureePause = 0;
				$deltaI_Pause = 0;
				$tabGeofence[$user]['alerteEnCours'] = 0;
				$tabGeofence[$user]['debTime'] = 0;
				$tabGeofence[$user]['cloture'] = 0;
				$tabGeofence[$user]['dureeTotale'] = 0;
				$tabGeofence[$user]['sommePause'] = 0;
				$tabGeofence[$user]['distanceTotale'] = 0;
				$tabGeofence[$user]['denivelePlus'] = 0;
				$tabGeofence[$user]['deniveleMoins'] = 0;
			}

		} // ******** FIN $i > 0 ********

		// Enregistrement pour affichage
		$message = " $i $activite $user => ".date('d-m H:i:s', $timeCourante) ." - $SSID - ".round($ecart, 3)." / ".round($tabGeofence[$user]['distanceTotale'],4)." km - $dureeEcart sec - $vitesseEcart km/h - Pause : $dureePause / ".$tabGeofence[$user]['sommePause']." mn -  Alt / Vit : $altitude / $vitesseAltitude  m/s. (az:$azimut)";

		if (!$inhibe) {
			// latlngs et complément en tables secondaires pour éviter dépassement de size de la SGBD
			$tabGeofence_L .= "[$latlng,$timeCourante],";
			$tabGeofence_C .= "[".round($tabGeofence[$user]['distanceTotale'], 3).",$dureeEcart,$altitude,'$activite',$pcBatterie,$dureePause,$deltaI_Pause],";

			// Mémo des oldValue
			$oldTimeCourante = $timeCourante;
			$oldActivite = $activite;
			$oldLatitude = $latitude;
			$oldLongitude = $longitude;
			$oldAltitude = $altitude;
			$oldVitesseEcart = $vitesseEcart;

			if ($scenario->getConfiguration('logmode') == 'realtime') { mg::message('', "  $message"); }
		} else { mg::message('', "* $message"); }
	}

	mg::setVar("tabGeofence_L_$user", $tabGeofence_L);
	mg::setVar("tabGeofence_C_$user", $tabGeofence_C);
	$nbLignes = $i-1;
	$lastLatLng_User = $latlng;

	// *********************************** CALCUL DE LA SYNTHESE DE L'ENTRAINEMENT ************************************
	if ($tabGeofence[$user]['debTime'] != 0) {
	$tabGeofence[$user]['dureeTotale'] = (($tabGeofence[$user]['cloture'] != 0) ? $tabGeofence[$user]['cloture'] : $oldTimeCourante) - $tabGeofence[$user]['debTime'];

		$tabGeofence[$user]['dureeMouvement'] = $tabGeofence[$user]['dureeTotale'] - $tabGeofence[$user]['sommePause']*60;
		if ($tabGeofence[$user]['distanceTotale'] == 0 || $tabGeofence[$user]['dureeMouvement'] == 0) {
			$tabGeofence[$user]['vitesseMoyenneTotale'] = 0;
			$tabGeofence[$user]['vitesseMoyenneMouvement'] = 0;
		}
		else {
			$tabGeofence[$user]['vitesseMoyenneTotale'] = $tabGeofence[$user]['distanceTotale'] / $tabGeofence[$user]['dureeTotale'] * 3600;
			$tabGeofence[$user]['vitesseMoyenneMouvement'] = $tabGeofence[$user]['distanceTotale'] / $tabGeofence[$user]['dureeMouvement'] * 3600;
		}
	}

	// Calcul des lastValues
	$tabGeofence[$user]['lastTime'] = $timeCourante;
	$tabGeofence[$user]['lastPcBatterie'] = $pcBatterie;
	$tabGeofence[$user]['lastSSID'] = $SSID;
}

?>