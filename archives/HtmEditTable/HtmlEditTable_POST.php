<head>
<!-- RETOUR A LA PAGE PRECEDENTE, mettre 1 au minimum pour laisser le temps à la page d'ête générée - 5/10 pour pouvoir lire les messages -->
<!-- <meta http-equiv="refresh" content="0;<?php echo $_SERVER['HTTP_REFERER']; ?>" /> <!-- -->
</head>

<style>
.page
{
	font-family: Verdana; /* police */
	color:black;
	font-weight: bold; /* fonte grasse du texte */
	text-align:center; /* alignement horizontal */
	font-size: 2em; /* taille de police relative */
}
</style>

<body style="background-color:#FFF4C4;"> <!-- #FFD264;#FFF4C4 -->
<div class='page'>
	<img src='wait.gif'>
<br><br></div></body>

// <?php
// *************************************************** CODE PHP APPELE *************************************************
	$lngSegments = 2000; // Longueur maximum transmissible via l'API Jeedom
	
	$IP = trim(htmlspecialchars($_POST['IP'])); 				// IP de jeedom
	$apikey = trim(htmlspecialchars($_POST['apikey'])); 		// apikey de jeedom
	$numScenario = intval($_POST['numScenario']); 				// Scénario à appeler
	$submit = trim(htmlspecialchars($_POST['submit']));			// Ordre à éxécuter
	$nomTab = trim(htmlspecialchars($_POST['nomTab']));			// Nom de la table
	$nomTabOrg = trim(htmlspecialchars($_POST['nomTabOrg']));	// Nom de la table d'origine
	$message = trim(@$_POST['message']).trim(@$_POST['message2']);// Contenu de la table 
	$nbSegments = 0;
	
	$lngMessage = strlen($message);
		echo ("( Nombre de variable du _POST " . count($_POST) . " - Action : '$submit' - nomTab : '$nomTab' - nomTabOrg : '$nomTabOrg' - nbSegments : '$nbSegments' - lngMessage : '$lngMessage' )<br><br>");
	// ENREGISTRER
	if ($submit == 'Enregistrer') {
		if ($nomTabOrg != '') { $nomTab = $nomTabOrg; } // Sécurité pour éviter d'enregistrer sous un autre nom
		if ($lngMessage > 10) {
			$nbSegments = min(20, round(($lngMessage / $lngSegments) +0.5));
			$tabMessage = str_split($message, $lngSegments);
			for ($i=0; $i<$nbSegments; $i++) {
				API_Var($IP, $apikey, "_retFile$i", trim($tabMessage[$i], '"'));
			}
		} else {
			echo strtoupper("<br>*************** MESSAGE VIDE ACTION '$submit' ANNULEE !!! ***************<br>");
			return;
		}
	} 
		echo strtoupper("<br>*************** ACTION '$submit' EN COURS ***************<br>");
	
	// LANCEMENT SCENARIO DE GENERATION DU TABLEAU
	API_Scenario($IP, $apikey, $numScenario, 'start', "submit='$submit' nomTab='$nomTab' nbSegments='$nbSegments' lngMessage='$lngMessage'");

	// ATTENTE RETOUR
	$ret = '0';
	$ret = API_Var($IP, $apikey, '_htmlOK', '0');
	for ($i=0;$i<60 && $ret!='1';$i++) {
		$ret = API_Var($IP, $apikey, '_htmlOK');
		usleep(0.5*1000000);
	}

	// Redirection PHP en fin de process
	header("Status: 301 Moved Permanently", false, 301);
	header("Location: {$_SERVER['HTTP_REFERER']}");
	exit();

// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------
// Lecture et Enregistrement d'une variable en BdD (selon la présence ou non de '$Value'
function API_Var($IP, $apikey, $Name, $Value='') {
		$params = array(	'apikey'=>$apikey,
							'type'=>'variable',
							'name'=>$Name,
							'value'=>$Value);
		$urlJeedom = "http://$IP/core/api/jeeApi.php?" . http_build_query($params);
		return file_get_contents($urlJeedom);
//		echo $url;
	}
// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------
// Lancement d'un scénario
function API_Scenario($IP, $apikey, $ID, $Action='start', $tag='') {
		echo strtoupper("<br>***************< $Action du scénario N° '$ID' ***************<br>");
		$params = array(	'apikey'=>$apikey,
							'type'=>'scenario',
							'id'=>$ID,
							'action'=>$Action,
							'tags'=>$tag
							);
		$urlJeedom = "http://$IP/core/api/jeeApi.php?" . http_build_query($params);
		$Value = trim(file_get_contents($urlJeedom));
	}

?>
