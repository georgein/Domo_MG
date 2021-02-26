
jQuery.fn.extend({

  digicodeOptions: function(callback, delayReset) {

    this.html(

	'<html><body><div class=digicodeOptions>'

+'<table class=tableau style=width:150px;>'
	+'<colgroup>'
			+'<col style=width:60px>'
			+'<col style=width:90px>'
		+'</colgroup>'

	+'<tr>'
		+'<th class=titre colspan=2>Liste des Codes</th>'
	+'</tr>'

	+'<tr>'
		+'<td class=t-0-1>Modif</td>'
		+'<td class=t-0-2>Code</td>'
	+'</tr>'

	+'<tr>'
		+'<td class=bouton>MG</td>'
		+'<td class=c-1-2>#31416</td>'
	+'</tr>'

	+'<tr>'
		+'<td class=bouton>NR</td>'
		+'<td class=c-0-2>121060</td>'
	+'</tr>'

	+'<tr>'
		+'<td class=bouton>CB</td>'
		+'<td class=c-1-2>111111</td>'
	+'</tr>'

	+'<tr>'
		+'<td class=bouton>PS</td>'
		+'<td class=c-0-2>654321</td>'
	+'</tr>'

	+'<tr>'
		+'<td class=bouton>Invité</td>'
		+'<td class=c-1-2>#1234#</td>'
	+'</tr>'

	+'<tr>'
		+'<td class=bouton>Options</td>'
		+'<td class=c-0-2>31416#</td>'
	+'</tr>'

+'</table>'

	+'<table class=tableau style=width:auto;>'
		+'<td class=bouton>QUITTER</td>'
		+'</table>'
		+'*** F5 - Refresh ***'

	+'</div></body></html>'

	);    this.addClass('digicodeOptions');

    this.jeedomExecute = $.isFunction(callback) ? callback : (function () {}) ;
    this.keys = this.find('.bouton');
    this.Displays = this.find('.digiEvent li');
    this.inputs = [];
    this.timer = null;
    this.delayReset *= 1000;

    this.displayInputs = (function() {
      this.Displays.removeClass('digiFilled digiFilledOK');
      $.each(this.inputs, (function(i, e) {
        this.Displays.eq(i).addClass('digiFilled');
      }).bind(this));
    }).bind(this);

    this.clearCode = (function() {
      this.inputs = [];
      this.displayInputs();
      clearInterval(this.timer);
    }).bind(this);

    this.resetTimer = (function(resetTimer) {
      if (this.timer != null) {
        clearInterval(this.timer);
      }
     this.timer = setInterval(this.clearCode, this.delayReset);
    }).bind(this);

    this.codeReady = (function() {
      this.jeedomExecute(this.inputs.join(''));
        setTimeout((function() {
          this.Displays.addClass('digiFilledOK');
        }).bind(this), 200);
        setTimeout((function() {
          this.clearCode();
        }).bind(this), 500);
    }).bind(this);

    this.keys.on(('ontouchstart' in document.documentElement) ? "touchstart" : "click", (function(e) {
      var el = $(e.currentTarget);
      if (el.hasClass('digiReset')) {
        this.clearCode();
      }
      else {
        el.addClass('digiSel');
        this.inputs.push(el.text());
        this.displayInputs();
        this.resetTimer();
        if (this.inputs.length == 1) {
          this.codeReady();
        }
      }
    }).bind(this));

    this.keys.on('mouseup mouseleave touchend', function() {
      var el = $(this);
      if (!el.hasClass('digiReset')) {
        setTimeout(function() {
          el.removeClass('digiSel');
        }, 150);
      }
    });

  }
});
