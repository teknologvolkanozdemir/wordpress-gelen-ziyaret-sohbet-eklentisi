(function () {
	var root = document.getElementById('zs-chat');
	if (!root || !window.zsChat) { return; }
	var $ = function (id) { return document.getElementById(id); };
	var toggle = $('zs-toggle'), panel = $('zs-panel'), log = $('zs-log'),
		form = $('zs-form'), input = $('zs-input'), status = $('zs-status'), close = $('zs-close');
	var KEY = 'zsSession', session = null, last = 0, timer = null;
	try { session = localStorage.getItem(KEY); } catch (e) {}

	function add(who, text) {
		var p = document.createElement('p');
		p.className = 'zs-msg ' + (who === 'in' ? 'zs-visitor' : 'zs-agent');
		var s = document.createElement('strong');
		s.textContent = who === 'in' ? 'Siz: ' : 'Temsilci: ';
		p.appendChild(s);
		p.appendChild(document.createTextNode(text));
		log.appendChild(p);
		log.scrollTop = log.scrollHeight;
	}
	function say(t) { status.textContent = ''; setTimeout(function () { status.textContent = t; }, 50); }

	function poll() {
		if (!session) { return; }
		fetch(zsChat.rest + 'messages?session=' + encodeURIComponent(session) + '&after=' + last)
			.then(function (r) { return r.json(); })
			.then(function (d) {
				(d.messages || []).forEach(function (m) {
					last = Math.max(last, parseInt(m.id, 10));
					if (!rendered[m.id]) { rendered[m.id] = 1; add(m.direction, m.message); }
				});
			}).catch(function () {});
	}
	var rendered = {};

	function open() {
		panel.hidden = false;
		toggle.setAttribute('aria-expanded', 'true');
		input.focus();
		poll();
		timer = setInterval(poll, 4000);
	}
	function shut() {
		panel.hidden = true;
		toggle.setAttribute('aria-expanded', 'false');
		clearInterval(timer);
		toggle.focus();
	}
	toggle.addEventListener('click', function () { panel.hidden ? open() : shut(); });
	close.addEventListener('click', shut);
	panel.addEventListener('keydown', function (e) { if (e.key === 'Escape') { shut(); } });

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		var text = input.value.trim();
		if (!text) { say('Lütfen bir mesaj yazın.'); input.focus(); return; }
		var body = new URLSearchParams({ message: text });
		if (session) { body.append('session', session); }
		fetch(zsChat.rest + 'send', { method: 'POST', body: body })
			.then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
			.then(function (x) {
				if (!x.ok) { say(x.d.message || 'Mesaj gönderilemedi.'); return; }
				session = x.d.session;
				try { localStorage.setItem(KEY, session); } catch (e) {}
				rendered[x.d.id] = 1;
				last = Math.max(last, x.d.id);
				add('in', text);
				input.value = '';
				say('Mesajınız gönderildi.');
				input.focus();
			}).catch(function () { say('Mesaj gönderilemedi.'); });
	});
})();
