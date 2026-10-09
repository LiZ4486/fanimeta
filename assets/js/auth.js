/**
 * 自建账号系统前端脚本
 * - 登录弹窗：打开 / 关闭 / Ajax 提交
 * - 注册页：获取邮箱验证码 + 倒计时
 */
(function () {
	'use strict';

	var cfg = window.fanimetaAuth || {};
	var labels = cfg.labels || {};

	function text(key, fallback) {
		return labels[key] || fallback;
	}

	function format(template, value) {
		return String(template).replace('%d', value);
	}

	/* ======================================================================
	 * 一、登录弹窗
	 * ==================================================================== */

	var modal = document.getElementById('fanimeta-auth-modal');

	if (modal) {
		var dialog = modal.querySelector('.auth-modal-dialog');
		var lastFocused = null;

		function openModal() {
			lastFocused = document.activeElement;
			modal.hidden = false;
			document.body.classList.add('auth-modal-open');

			var first = modal.querySelector('input[name="user_login"]');
			if (first) {
				window.setTimeout(function () {
					first.focus();
				}, 30);
			}
		}

		function closeModal() {
			modal.hidden = true;
			document.body.classList.remove('auth-modal-open');

			if (lastFocused && typeof lastFocused.focus === 'function') {
				lastFocused.focus();
			}
		}

		// 打开：所有带 data-auth-open="login" 的链接
		document.addEventListener('click', function (event) {
			var trigger = event.target.closest ? event.target.closest('[data-auth-open="login"]') : null;
			if (!trigger) {
				return;
			}
			// 未启用脚本时链接本身仍可跳转到 /login/ 独立页面
			event.preventDefault();
			openModal();
		});

		var closeBtn = modal.querySelector('.auth-modal-close');
		if (closeBtn) {
			closeBtn.addEventListener('click', closeModal);
		}

		var mask = modal.querySelector('.auth-modal-mask');
		if (mask) {
			mask.addEventListener('click', closeModal);
		}

		document.addEventListener('keydown', function (event) {
			if ('Escape' === event.key && !modal.hidden) {
				closeModal();
			}
		});

		// 点击弹窗外部区域关闭
		modal.addEventListener('click', function (event) {
			if (dialog && !dialog.contains(event.target)) {
				closeModal();
			}
		});

		/* ---------- Ajax 登录 ---------- */
		var loginForm = document.getElementById('fanimeta-modal-login-form');

		if (loginForm) {
			var loginTip = document.getElementById('fanimeta-modal-tip');
			var loginBtn = loginForm.querySelector('.auth-submit');

			function say(message, isError) {
				if (!loginTip) {
					return;
				}
				loginTip.textContent = message;
				loginTip.className = 'auth-notice ' + (isError ? 'auth-notice-error' : 'auth-notice-success');
				loginTip.hidden = false;
			}

			loginForm.addEventListener('submit', function (event) {
				event.preventDefault();

				if (!cfg.ajax) {
					loginForm.submit();
					return;
				}

				var body = new URLSearchParams();
				body.append('action', 'fanimeta_ajax_login');
				body.append('nonce', cfg.nonce || '');
				body.append('user_login', loginForm.querySelector('[name="user_login"]').value.trim());
				body.append('user_password', loginForm.querySelector('[name="user_password"]').value);
				body.append('redirect_to', window.location.href);

				var remember = loginForm.querySelector('[name="rememberme"]');
				if (remember && remember.checked) {
					body.append('rememberme', '1');
				}

				if (loginBtn) {
					loginBtn.disabled = true;
					loginBtn.textContent = text('submitting', '登录中…');
				}
				if (loginTip) {
					loginTip.hidden = true;
				}

				window.fetch(cfg.ajax, {
					method: 'POST',
					credentials: 'same-origin',
					body: body
				})
					.then(function (res) {
						return res.json();
					})
					.then(function (json) {
						if (json && json.success && json.data && json.data.redirect) {
							say('登录成功，正在跳转…', false);
							window.location.href = json.data.redirect;
							return;
						}
						say((json && json.data && json.data.message) || '登录失败，请稍后重试。', true);
						if (loginBtn) {
							loginBtn.disabled = false;
							loginBtn.textContent = '登录';
						}
					})
					.catch(function () {
						say('网络异常，请稍后重试。', true);
						if (loginBtn) {
							loginBtn.disabled = false;
							loginBtn.textContent = '登录';
						}
					});
			});
		}
	}

	/* ======================================================================
	 * 二、注册页：获取邮箱验证码
	 * ==================================================================== */

	var sendBtn = document.getElementById('fanimeta-send-code');
	var codeTip = document.getElementById('fanimeta-code-tip');

	if (sendBtn && codeTip) {
		var regForm = document.getElementById('fanimeta-register-form');
		var emailInput = regForm ? regForm.querySelector('[name="user_email"]') : null;
		var timer = null;

		function sayCode(message, ok) {
			codeTip.textContent = message;
			codeTip.className = 'auth-code-tip ' + (ok ? 'is-ok' : 'is-error');
		}

		function countdown(seconds) {
			var left = seconds;
			sendBtn.disabled = true;
			sendBtn.textContent = format(text('countdown', '%d 秒后可重发'), left);

			timer = window.setInterval(function () {
				left--;
				if (left <= 0) {
					window.clearInterval(timer);
					sendBtn.disabled = false;
					sendBtn.textContent = text('resend', '重新获取');
					return;
				}
				sendBtn.textContent = format(text('countdown', '%d 秒后可重发'), left);
			}, 1000);
		}

		sendBtn.addEventListener('click', function () {
			var email = emailInput ? emailInput.value.trim() : '';

			if (!email || email.indexOf('@') === -1) {
				sayCode('请先填写正确的邮箱地址。', false);
				if (emailInput) {
					emailInput.focus();
				}
				return;
			}

			sendBtn.disabled = true;
			sayCode(text('sending', '正在发送…'), true);

			var body = new URLSearchParams();
			body.append('action', 'fanimeta_email_send_code');
			body.append('nonce', cfg.emailNonce || '');
			body.append('email', email);

			window.fetch(cfg.ajax, {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			})
				.then(function (res) {
					return res.json();
				})
				.then(function (json) {
					if (json && json.success) {
						sayCode(json.data.message, true);
						countdown(json.data.cooldown || 60);
					} else {
						sayCode((json && json.data && json.data.message) || '发送失败，请稍后重试。', false);
						sendBtn.disabled = false;
					}
				})
				.catch(function () {
					sayCode('网络异常，请稍后重试。', false);
					sendBtn.disabled = false;
				});
		});
	}
})();
