/*

public class HtmlEditTable {

  public:

  HtmlEditTable(object o);
  void AppendTo(control parent);
  Array LineNames();
  Array LineNames(Array t);
  Array ColumnNames();
  Array ColumnNames(Array t);
  object Dimensions();
  object Dimensions(object d);
  Array Line();
  Array Line(object o);
  Array Column();
  Array Column(object o);
  void Clean();
  void Populate(Array data);
  void Build(object o);
  T Data(string col, string row);
  T Data(string col, string row, T data);
  Array AllData();
};
*/

var HtmlEditTable = null;

// ------------------------------------------------- Ajout des boutons ------------------------------------------------
var buttonUp = "<td><button id='bt-up' class='bt-up'>↑</button></td>";
var buttonDown = "<td><button id='bt-down' class='bt-down'>↓</button></td>";
var buttonAdd = "<td><button id='bt-add' class='bt-add'>+</button></td>";
var buttonDel = "<td><button id='bt-del' class='bt-del'><img src="+imgPoubelle+"></button></td>";
var buttonLeft = "<td><button id='bt-left' class='bt-left'>Left</button></td>";
var buttonRight = "<td><button id='bt-right' class='bt-right'>Right</button></td>";

var buttonTete = '';

// pas de button pour les tables préfixée de '_'
if (nomTab[0] != '_') {
	Addcolonne(buttonUp);
	Addcolonne(buttonDown);
	Addcolonne(buttonAdd);
	Addcolonne(buttonDel);
}

function reload() { document.location.reload(); }

function nombreItem() {
	var TR = document.getElementById('tableHTML').getElementsByTagName('tr');
	return LastCellule = document.getElementById('tableHTML').getElementsByTagName('tr')[1].getElementsByTagName('td').length + (nomTab[0] != '_' ? -4 : 0);
}
	
// ----------------------------------------------------- API JEEDOM ---------------------------------------------------
apiJeedom = 'w35cb9cmgg2ehbsbca1h';
apiVirtual= 'M60ifHEUrDqIssSbVHGaAfF0FYLKXfQ5';

	function getSetVar(name, value) {
		return $.get('/core/api/jeeApi.php?apikey='+apiJeedom+'&type=variable&name='+name+'&value=\"'+value+'\"');
	}

	function getCmd(id) {
		return $.get('/core/api/jeeApi.php?apikey='+apiJeedom+'&type=cmd&id='+id);
	}
// --------------------------------------------- BOUTON DE FONCTION THEAD ---------------------------------------------
	function bt_left() {
		buttonTete = 'bt_left';
	}
	function bt_right() {
		buttonTete = 'bt_right';
	}
	function bt_add() {
		buttonTete = 'bt_add';
	}
	function bt_del() {
		buttonTete = 'bt_del';
	}

// ---------------------------- IDENTIFICATION DES CLIC SUR THEAD POUR LANCEMENT D'ACTION -----------------------------
	$('thead').on('click','th', (function () {
		var eTD		= this
//			, eTR	  = eTD.parentNode
//			, ligne	  = eTR.rowIndex
			, colonne = eTD.cellIndex
			;
			//alert('THEAD - colonne : ' + colonne);

		// ********** MOVE LEFT **********
		if (buttonTete == 'bt_left') {
			if ( colonne <= nbKeys) { return; }
			inverserColonne(colonne, colonne-1);
		}
		// ********** MOVE RIGHT **********
		else if (buttonTete == 'bt_right') {
			if ( colonne >= nombreItem()-1) { return; }
			inverserColonne(colonne, colonne+1);
		}
		// ********** ADD COLONNE **********
		else if (buttonTete == 'bt_add') {
			var TR = document.getElementById('tableHTML').getElementsByTagName('tr');
			for(var i = 0; i < TR.length ; i++){
				var newCell = TR[i].insertCell(colonne+1);
				if (i>0) {
					newCell.innerHTML = ' ';
				} else {
					newCell.innerHTML = 'colonne_'+(colonne+2)+boutonFonctionsColonnes;
				}
			}
		}
		// ********** DELETE COLONNE **********
		else if (buttonTete == 'bt_del') {
			var tble = document.getElementById('tableHTML');
			var row = tble.rows;
			var i = colonne;
			for (var j = 0; j < row.length; j++) {
				row[j].deleteCell(i);
			}
		}
		// ********** EDITION DU TITRE DE LA COLONNE **********
		else {
			var rows = document.getElementById('tableHTML').tHead.rows;
			var cells = rows[0].cells;
			var cell = cells[colonne];
			var nomColonne = (cell.innerHTML.split('<'))[0];
			var nomColonne = prompt("Donnez le nouveau nom de la colonne '"+nomColonne+"'");
			if (nomColonne != null) {
				cell.innerHTML = nomColonne+boutonFonctionsColonnes;
			}
		}
		buttonTete = '';
	var tableEdit = new HtmlEditTable({'table': tableHTML});
	}));

// ---------------------------- IDENTIFICATION DES CLIC SUR TBODY POUR LANCEMENT D'ACTION -----------------------------
	$('tbody').on('click','td', (function () {
		var eTD		= this
			, eTR	  = eTD.parentNode
			, ligne	  = eTR.rowIndex
			, colonne = eTD.cellIndex
			;

//			alert('TBODY - ligne : ' +ligne + ' - colonne : ' + colonne);

		nbItem = nombreItem();
		// ********** MOVE UP **********
		if (colonne == (nbItem+0)) {
			deplacerLigne(ligne, ligne-1);
		}
		// ********** MOVE DOWN **********
		if (colonne == (nbItem+1)) {
			deplacerLigne(ligne, ligne+2);
		}
		// ********** ADD LIGNE **********
		if (colonne == (nbItem+2)) {
			// Init de la section
			var ligne_ = document.getElementById("tableHTML").rows[ligne];
			var cellules = ligne_.cells;
			var section = cellules[0].innerHTML;
//			var tr = '<tr><td>'+section+'</td><td>_REM_'+ligne;
			var tr = '<tr><td>'+section+'</td><td>';
			// on ajoute le nb de cellule nécessaire depuis l'en tête
			var rows = document.getElementById('tableHTML').tHead.rows;
			var cells = rows[0].cells;
			for (var x=2, xmax=(cells.length-4); x<xmax; x++){
				tr += '<td> </td>';
			}
			tr += buttonUp + buttonDown + buttonAdd + buttonDel+'</tr>';

			var newRow = document.getElementById('tableHTML').insertRow(ligne);
			newRow.innerHTML = tr;

		}
		// ********** DELETE LIGNE **********
		if (colonne == (nbItem+3)) {
			document.getElementById('tableHTML').deleteRow(ligne);
		}
	var tableEdit = new HtmlEditTable({'table': tableHTML});
	}));

// ------------------------------------------------- DEPLACER UNE LIGNE -----------------------------------------------
// http://pub.phyks.me/sdz/sdz/maitriser-les-tableaux-html-avec-javascript.html
function deplacerLigne(source, cible) {
	if (cible <= 0) { return; }
	var ligne = document.getElementById("tableHTML").rows[source];
	var nouvelle = document.getElementById("tableHTML").insertRow(cible);
	var cellules = ligne.cells;
	for(var i=0; i<cellules.length; i++) {
		nouvelle.insertCell(-1).innerHTML += cellules[i].innerHTML;
	}
	document.getElementById("tableHTML").deleteRow(ligne.rowIndex);
	var tableEdit = new HtmlEditTable({'table': tableHTML});
}

	// ----------------------------------------------- INVERSER COLONNE	 ----------------------------------------------
function inverserColonne(a,b) {
	var t=document.getElementById("tableHTML");
	var tmp;
	for ( var l=0;l<t.rows.length;l++ )
		{
			tmp=t.rows[l].cells[a].innerHTML;
			t.rows[l].cells[a].innerHTML=t.rows[l].cells[b].innerHTML;
			t.rows[l].cells[b].innerHTML=tmp;
	}
	var tableEdit = new HtmlEditTable({'table': tableHTML});
}

	// ------------------------------------- INSERTION COLONNE A L'EXTREME DROITE -------------------------------------
	function Addcolonne(txtButton){
		//récupération des lignes du tableau pour ajouter une cellule à chacune
		var TR = document.getElementById('tableHTML').getElementsByTagName('tr');
		// N° dernière colonne de la première ligne de data
		var LastCellule = document.getElementById('tableHTML').getElementsByTagName('tr')[1].getElementsByTagName('td').length;
		for(var i = 1; i < TR.length ; i++){
			var newCell = TR[i].insertCell(LastCellule);
			newCell.innerHTML = txtButton;
		}
	}

// ///////////////////////////////////////////////// FONCTIONS FILTRE /////////////////////////////////////////////////
// Source: https://blog.pagesd.info/2019/09/30/rechercher-filtrer-table-javascript/

(function () {
  "use strict";

  var TableFilter = (function () {
	var search;

	function dquery(selector) {
	  // Renvoie un tableau des éléments correspondant au sélecteur
	  return Array.prototype.slice.call(document.querySelectorAll(selector));
	}

	function onInputEvent(e) {
	  // Récupère le texte à rechercher
	  var input = e.target;
	  search = input.value.toLocaleLowerCase();
	  // Retrouve les lignes où effectuer la recherche
	  // (l'attribut data-table de l'input sert à identifier la table à filtrer)
	  var selector = input.getAttribute("data-table") + " tbody tr";
	  var rows = dquery(selector);
	  // Recherche le texte demandé sur les lignes du tableau
	  [].forEach.call(rows, filter);
	  // Mise à jour du compteur de ligne (s'il y en a un de défini)
	  // (l'attribut data-count de l'input sert à identifier l'élément où afficher le compteur)
	  var writer = input.getAttribute("data-count");
	  if (writer) {
		// S'il existe un attribut data-count, on compte les lignes visibles
		var count = rows.reduce(function (t, x) { return t + (x.style.display === "none" ? 0 : 1); }, 0);
		// Puis on affiche le compteur
		dquery(writer)[0].textContent = count;
	  }
	}

	function filter(row) {
	  // Mise en cache de la ligne en minuscule
	  if (row.lowerTextContent === undefined)
		row.lowerTextContent = row.textContent.toLocaleLowerCase();
	  // Masque la ligne si elle ne contient pas le texte recherché
	  row.style.display = row.lowerTextContent.indexOf(search) === -1 ? "none" : "table-row";
	}

	return {
	  init: function () {
		// Liste des champs de saisie avec un attribut data-table
		var inputs = dquery("input[data-table]");
		[].forEach.call(inputs, function (input) {
		  // Déclenche la recherche dès qu'on saisi un filtre de recherche
		  input.oninput = onInputEvent;
		  // Si on a pas déjà une valeur (initialisation), on lance la recherche
		  if (input.value === "") input.oninput({ target: input });
		});
	  }
	};

  })();

//----------------------------------------------------------------
  //console.log(document.readyState);
	document.addEventListener('readystatechange', function() {
		if (document.readyState === 'complete') {
//		console.log(document.readyState);
			TableFilter.init();
		}
	});
//----------------------------------------------------------------

  TableFilter.init();
})();
// --------------------------------------------------------------------------------------------------------------------

// ///////////////////////////////////////// FONCTIONS AFFICHAGE - NAVIGATION /////////////////////////////////////////
// https://falola.developpez.com/tutoriels/javascript/htmledittable-v1-0/?page=P3#LIII
// https://falola.developpez.com/tutoriels/javascript/htmledittable-v2-0/?page=P3
(function(){
  HtmlEditTable = function(){
	if (arguments.length > 0 && arguments[0].table){
	  this.Transform(arguments);
	}
	else{
	  this.control = document.createElement("table");
	  if (arguments.length > 0){
		this.Build(arguments[0]);
	  }
	}

	this.control.cellSpacing = 0;
	this.control.cellPadding = 0;
	this.control.className = "HtmlEditTable";
	this.activeCell = null;
  };

  HtmlEditTable.prototype = {
	Transform: function(args){
	  this.columns = [];
	  this.lines = [];
	  this.headers = [];
	  this.control = args[0].table;
	  this.caption = this.control.caption;
	  for (var i=0, imax=this.control.rows[0].cells.length; i<imax; i++){
		  this.columns.push("" + i);
	  }
	  for (var i=0, imax=this.control.rows.length; i<imax; i++){
		this.lines.push("" + i);
	  }

	  if (this.control && this.control.tBodies){
		for (var i=0, imax=this.control.tBodies.length; i<imax; i++){
		  var tBody = this.control.tBodies[i];
		  for (var j=0, jmax=tBody.rows.length; j<jmax; j++){
			for (var k=0, kmax=tBody.rows[j].cells.length; k<kmax; k++){
			  var cell = tBody.rows[j].cells[k];
			  cell.tabIndex = 0;
			  HtmlEditTableHelper.CellInitialize(this, cell);

						  if (cell.childNodes.length == 0){
								cell.appendChild(document.createTextNode(""));
						  }
			}
		  }
		}
	  }

	  if (this.control){
		Tools.RemoveTextNode(this.control);
			if (this.control.tHead){
			  var tHead = this.control.tHead;
		  Tools.RemoveTextNode(tHead);
		  for (var i=0, imax=tHead.rows.length; i<imax; i++){
			Tools.RemoveTextNode(tHead.rows[i]);
		  }
			}
			if (this.control.tBodies){
		  for (var i=0, imax=this.control.tBodies.length; i<imax; i++){
			var tBody = this.control.tBodies[i];
				  Tools.RemoveTextNode(tBody);
			for (var j=0, jmax=tBody.rows.length; j<jmax; j++){
			  Tools.RemoveTextNode(tBody.rows[j]);
			}
			  }
		}
		if (this.control.tFoot){
		  var tFoot = this.control.tFoot;
		  Tools.RemoveTextNode(tFoot);
		  for (var i=0, imax=tFoot.rows.length; i<imax; i++){
			Tools.RemoveTextNode(tFoot.rows[i]);
			  }
		}
	  }
	},
	Build: function(){
	  if (arguments.length > 0){
		this.Clean();

		var o = arguments[0];

		this.Dimensions(o);

		if (o.Xn){
		  this.columns = o.Xn;
		}

		if (o.Yn){
		  this.lines = o.Yn;
		}

		if (o.head){
		  this.headers = o.head;
		}

		if (o.caption){
		  this.caption = o.caption;
		}

		if (o.data){
		  this.Populate(o.data);
		}
		else {
		  this.Populate();
		}
	  }
	},
	Clean: function(){
	  Tools.Purge(this.control);
	  this.columns = [];
	  this.lines = [];
	  this.headers = [];
	  this.caption = "";
	},
	Dimensions: function(){
	  if (arguments.length > 0){
		this.columns = [];
		this.lines = [];

		if (arguments[0].X){
		  for (var i=0, imax=arguments[0].X; i<imax; i++){
			this.columns.push("" + i);
		  }
		}
		if (arguments[0].Y){
		  for (var i=0, imax=arguments[0].Y; i<imax; i++){
			this.lines.push("" + i);
		  }
		}
	  }
	  return { "x": this.columns.length, "y": this.lines.length };
	},
	Populate: function(){
	  if (this.caption){ this.control.createCaption().appendChild(document.createTextNode(this.caption));
	  }

	  if (this.lines.length > 0){
		for (var i=0, Y=this.lines.length; i<Y; i++){
		  var row = this.control.insertRow(i);

		  if (this.columns.length > 0){
			for (var j=0, X=this.columns.length; j<X; j++){
			  var cell = row.insertCell(j);
			  if (arguments.length > 0){
				cell.tabIndex = 0;
				HtmlEditTableHelper.CellInitialize(this, cell, arguments[0][j+i*X]);
			  }
			}
		  }
		}
	  }

	  if (this.headers.length == this.columns.length){
		var tHead = this.control.createTHead();
		var row = tHead.insertRow(0);
		for (var i=0, imax=this.headers.length; i<imax; i++){
		  var cell = row.insertCell(i);
		  cell.appendChild(document.createTextNode(this.headers[i]));
		}
	  }
	},
	AppendTo: function(parent){
	  parent.appendChild(this.control);
	},
/*	AllData_old: function(){
	  var data = [];
	  var rows = this.control.tBodies[0].rows;
	  for (var y=0, ymax=rows.length; y<ymax; y++){
		var cells = rows[y].cells;
		for (var x=0, xmax=cells.length; x<xmax; x++){
		  data.push(cells[x].firstChild.data);
		}
	  }
	  return data;
	},*/

	AllData: function(){
		var data = [];
		var nbItems = nombreItem();
		// Export en Tête
		var rows = this.control.tHead.rows;
		for (var y=0, ymax=rows.length; y<ymax; y++){
			data.push('|');
			var cells = rows[y].cells;
		for (var x=0, xmax=/*cells.length-*/nbItems; x<xmax; x++){
				data.push(cells[x].firstChild.data);
			}
		}
		// Export ligne
		var rows = this.control.tBodies[0].rows;
		for (var y=0, ymax=rows.length; y<ymax; y++){
			data.push('|');
			var cells = rows[y].cells;
			for (var x=0, xmax=/*cells.length-*/nbItems; x<xmax; x++){
					data.push(cells[x].firstChild.data);
				}
			data.push('|');
		}
		return data;
	},

	LineNames: function(){
	  if (arguments.length > 0){
		this.lines = lines;
	  }
	  return this.lines;
	},
	ColumnNames: function(){
	  if (arguments.length > 0){
		this.columns = columns;
	  }
	  return this.columns;
	},
	Line: function(){
	  if (arguments.length == 0
		|| typeof arguments[0].name == "undefined"){
		return undefined;
	  }

	  var data = [];
	  var y = Tools.IndexOf(this.lines, arguments[0].name);

	  if (y > -1){
	  var cells = this.control.tBodies[0].rows[y].cells;

		if (typeof arguments[0].data != "undefined"){
		  data = arguments[0].data;
		  for (var i=0, imax=data.length; i<imax; i++){
			cells[i].firstChild.data = data[i];
		  }
		}
		else{
		  for (var x=0, xmax=cells.length; x<xmax; x++){
			data.push(cells[x].firstChild.data);
		  }
		}
	  }
	  else if (typeof arguments[0].data != "undefined"){
		this.lines.push(arguments[0].name);
		data = arguments[0].data;

		var row = this.control.insertRow(-1);
		for (var j=0, jmax=this.columns.length; j<jmax; j++){
		  var cell = row.insertCell(-1);
		  cell.tabIndex = 0;
		  HtmlEditTableHelper.CellInitialize(this, cell, data[j]);
		}
	  }

	  return data;
	},
	Column: function(){
	  if (arguments.length == 0
		|| typeof arguments[0].name == "undefined"){
		return undefined;
	  }

	  var data = [];
	  var rows = this.control.tBodies[0].rows;
	  var x = Tools.IndexOf(this.columns, arguments[0].name);

	  if (x > -1){
		if (typeof arguments[0].data != "undefined"){
		  data = arguments[0].data;
		  for (var i, imax=data.length; i<imax; i++){
			rows[i].cells[x].firstChild.data = data[i];
		  }
		}
		else{
		  this.lines.push(arguments[0].name);
		  for (var y=0, ymax=rows.length; y<ymax; y++){
			data.push(rows[y].cells[x].firstChild.data);
		  }
		}
	  }
	  else if (typeof arguments[0].data != "undefined"){
		this.columns.push(arguments[0].name);
		data = arguments[0].data;

		for (var y=0, ymax=rows.length; y<ymax; y++){
		  var cell = rows[y].insertCell(-1);
		  cell.tabIndex = 0;
		  HtmlEditTableHelper.CellInitialize(this, cell, data[y]);
		}

		var thead = this.control.getElementsByTagName("thead");
		if (thead.length > 0){
		  thead = thead[0];
		  var title = typeof arguments[0].head != "undefined" ? arguments[0].head : "";
		  var cell = thead.rows[0].insertCell(-1);
		  cell.appendChild(document.createTextNode(title));
		}
	  }

	  return data;
	},
	Data: function(column, line, data){
	  if (arguments.length < 2){
		return undefined;
	  }
	  var x, y;
	  x = Tools.IndexOf(this.columns, arguments[0]);
	  if (x > -1){
		y = Tools.IndexOf(this.lines, arguments[1]);
		if (y > -1){
		  if (arguments.length > 2){
			this.control.rows[y].cells[x].appendChild(document.createTextNode(arguments[2]));
		  }
		  return this.control.rows[y].cells[x].firstChild.data;
		}
	  }
	  return undefined;
	},
	ActiveCellChange: function(cell, focus){
	  if (this.activeCell){
		this.activeCell.className = null;
	  }
	  this.activeCell = cell;

	  if (cell){
		this.activeCell.className = "activeCell";
		if (focus){
		  this.activeCell.focus();
		}
	  }
	}
  };

  var HtmlEdit = function(value){
	var returnEvent = function(e){
	  var src = Tools.Target(e);
	  var cell = Tools.Node(src, "TD");
	  var grid = cell.grid;
	  Tools.Purge(cell);
	  HtmlEditTableHelper.CellInitialize(grid, cell, src.value);
	  cell.focus();
	};

	var escapeEvent = function(e){
	  var src = Tools.Target(e);
	  var cell = Tools.Node(src, "TD");
	  var grid = cell.grid;
	  Tools.Purge(cell);
	  HtmlEditTableHelper.CellInitialize(grid, cell, src.initialValue);
	  cell.focus();
	};

	var tabEvent = function(e){
	  var src = Tools.Target(e);
	  var cell = Tools.Node(src, "TD");
	  var shiftKey = Tools.SpecialKeys(e).ShiftKey;
	  var row = shiftKey ? cell.parentNode.previousSibling : cell.parentNode.nextSibling;

	  returnEvent(e);

	  if (row){
		cell = row.cells[cell.cellIndex];
		cell.grid.ActiveCellChange(cell, true);

		var htmlEdit = new HtmlEdit(cell.firstChild.data);
		htmlEdit.AppendTo(cell);
	  }
	  else{
		row = cell.parentNode;
		tBody = row.parentNode;
		if (shiftKey && cell.cellIndex>0){
		  row = tBody.lastChild;
		  cell = row.cells[cell.cellIndex-1];
		  cell.grid.ActiveCellChange(cell, true);
		  var htmlEdit = new HtmlEdit(cell.firstChild.data);
		  htmlEdit.AppendTo(cell);
		}
		else if (!shiftKey && row.cells.length-1 > cell.cellIndex){
		  row = tBody.firstChild;
		  cell = row.cells[cell.cellIndex+1];
		  cell.grid.ActiveCellChange(cell, true);
		  var htmlEdit = new HtmlEdit(cell.firstChild.data);
		  htmlEdit.AppendTo(cell);
		}
	  }
	};

	this.control = document.createElement("input");
	this.control.type = "text";
	this.control.className = "HtmlEdit";
	this.control.onblur = returnEvent;
	this.control.onkeydown = function(e){
	  switch(Tools.KeyCode(e)){
		case KeyCodes.RETURN: returnEvent(e); break;
		case KeyCodes.ESCAPE: escapeEvent(e); break;
		case KeyCodes.TAB: tabEvent(e); break;
	  }
	};

	this.control.value = value;
	this.control.initialValue = value;
  };

  HtmlEdit.prototype = {
	AppendTo: function(parent){
	  if (document.all){
		this.control.style.height = parent.clientHeight - 2*parent.clientTop + "px";
		this.control.style.width = parent.clientWidth - 2*parent.clientLeft + "px";
	  }
	  else{
		this.control.style.height = parent.offsetHeight - 2*parent.clientTop + "px";
		this.control.style.width = parent.offsetWidth - 2*parent.clientLeft + "px";
	  }
	  Tools.Purge(parent);
	  HtmlEditTableHelper.CellNeutralizeEvents(parent);
	  parent.appendChild(this.control);

	  this.control.select();
	  this.control.focus();
	}
  };

  var HtmlEditTableHelper = {
	CellInitialize: function (grid, cell, value){
	  if (cell){
		cell.grid = grid;
		cell.onclick = HtmlEditTableHelper.ClickHandler;
		cell.ondblclick = HtmlEditTableHelper.DblClickHandler;
			cell.onkeydown = HtmlEditTableHelper.KeydownHandler;
		if (typeof value != "undefined"){
		  cell.appendChild(document.createTextNode(value));
		}
	  }
	},

	CellNeutralizeEvents: function(cell){
	  if (cell){
		cell.onclick = null;
		cell.ondblclick = null;
		cell.onkeydown = null;
	  }
	},

	KeydownHandler: function(e){
	  var src = Tools.Node(Tools.Target(e), "TD");
	  if (!src){
		Tools.Event(e).returnValue = false;
		return false;
	  }

	  var NavDel = function(cell){
		cell.firstChild.data = "";
	  };

	  var NavLeft = function(cell){
		cell = Tools.Node(cell.previousSibling, "TD");
		if (cell){
		  cell.grid.ActiveCellChange(cell, true);
		}
	  };

	  var NavRight = function(cell){
		cell = Tools.Node(cell.nextSibling, "TD");
		if (cell){
		  cell.grid.ActiveCellChange(cell, true);
		}
	  };

	  var NavUp = function(cell){
		var row = cell.parentNode.previousSibling;
		if (row){
		  cell = row.cells[cell.cellIndex];
		  cell.grid.ActiveCellChange(cell, true);
		}
	  };

	  var NavDown = function(cell){
		var row = cell.parentNode.nextSibling;
		if (row){
		  cell = row.cells[cell.cellIndex];
		  cell.grid.ActiveCellChange(cell, true);
		}
	  };

	  var NavTab = function(cell){
		var shiftKey = Tools.SpecialKeys(e).ShiftKey;
		var row = shiftKey ? cell.parentNode.previousSibling : cell.parentNode.nextSibling;
		if (row){
		  cell = row.cells[cell.cellIndex];
		  cell.grid.ActiveCellChange(cell, true);
		}
		else{
		  row = cell.parentNode;
		  tBody = row.parentNode;
		  if (shiftKey && cell.cellIndex>0){
			row = tBody.lastChild;
			cell = row.cells[cell.cellIndex-1];
			cell.grid.ActiveCellChange(cell, true);
		  }
		  if (!shiftKey && row.cells.length-1 > cell.cellIndex){
			row = tBody.firstChild;
			cell = row.cells[cell.cellIndex+1];
			cell.grid.ActiveCellChange(cell, true);
		  }
		}
	  };

	  switch(Tools.KeyCode(e)){
		case KeyCodes.RETURN: HtmlEditTableHelper.DblClickHandler(e); break;
		case KeyCodes.DELETE: NavDel(src); break;
		case KeyCodes.LEFT: NavLeft(src); break;
		case KeyCodes.UP: NavUp(src); break;
		case KeyCodes.RIGHT: NavRight(src); break;
		case KeyCodes.DOWN: NavDown(src); break;
		case KeyCodes.TAB: NavTab(src); break;
	  }

	  returnValue = false;
	  return false;
	},

	DblClickHandler: function(e){
	  var src = Tools.Node(Tools.Target(e), "TD");
	  if (!src){
		Tools.Event(e).returnValue = false;
		return false;
	  }
	  var htmlEdit = new HtmlEdit(src.firstChild.data);
	  htmlEdit.AppendTo(src);
	  src.grid.ActiveCellChange(src, false);
	},

	ClickHandler: function(e){
	  var src = Tools.Node(Tools.Target(e), "TD");
	  if (!src){
		Tools.Event(e).returnValue = false;
		return false;
	  }
	  src.grid.ActiveCellChange(src, true);
	}
  };

  var Tools = {
	Purge: function(node){
	  while (node && node.hasChildNodes()){
		var child = node.firstChild;
		Tools.Purge(child);
		var attr = child.attributes;
		if (attr) {
		  var n;
		  var l = attr.length;
		  for (var i=0; i<l; i++){
			n = attr[i].name;
			if (typeof child[n] === 'function') {
			  child[n] = null;
			}
		  }
		}
		child = null;
		node.removeChild(node.firstChild);
	  }
	},

	Node: function(o, nodeName){
	  while(o && o.nodeName != nodeName.toUpperCase()){
		o = o.parentNode;
	  }
	  if (o){
		return o;
	  }
	  return undefined;
	},

	Event: function(e){
	  return window.event || e;
	},

	Target: function(e){
	  e = Tools.Event(e);
	  return e.srcElement || e.target;
	},

	KeyCode: function(e){
	  e = Tools.Event(e);
	  return e.keyCode || e.which;
	},

	IndexOf: function(array, value){
	  for (var i=0, imax=array.length; i<imax; i++){
		if (i in array && array[i] === value){
		  return i;
		}
	  }
	  return -1;
	},

	RemoveTextNode: function(o){
	  if (o == null){
		return;
	  }

	  for (var i=o.childNodes.length-1; i>-1; i--){
		var child = o.childNodes[i];
		if (child.nodeName == "#text"){
		  o.removeChild(child);
		}
	  }
	},

	SpecialKeys: function(e){
	  if (e.modifiers){
		var mString =(e.modifiers+32).toString(2).substring(3,6);
		return {
		  "ShiftKey": mString.charAt(0)=="1",
		  "CtrlKey": mString.charAt(1)=="1",
		  "AltKey": mString.charAt(2)=="1"
		};
	  }
	  else{
		return {
		  "ShiftKey": e.shiftKey,
		  "CtrlKey": e.ctrlKey,
		  "AltKey": e.altKey
		};
	  }
	}
  };

  var KeyCodes = {
	BACKSPACE: 8,
	TAB: 9,
	RETURN: 13,
	PAUSE: 19,
	CAPS_LOCK: 20,
	ESCAPE: 27,
	PAGE_UP: 33,
	PAGE_DOWN: 34,
	LEFT: 37,
	UP: 38,
	RIGHT: 39,
	DOWN: 40,
	INSERT: 45,
	DELETE: 46
  };
})();
