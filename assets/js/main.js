/* Fanimeta 主题脚本 */
(function () {
	'use strict';

	// 移动端汉堡菜单切换
	var navToggle = document.querySelector('.site-nav-toggle');
	var siteNav = document.querySelector('.site-nav');
	if (navToggle && siteNav) {
		navToggle.addEventListener('click', function () {
			siteNav.classList.toggle('site-nav-on');
		});
	}

	// 开屏公告弹窗：点击「我知道了」关闭并记住，此后不再展示
	var siteNotice = document.getElementById('site-notice');
	if (siteNotice) {
		var noticeConfirm = document.getElementById('site-notice-confirm');
		var closeNotice = function () {
			try {
				localStorage.setItem('fanimeta_notice_confirmed', '1');
			} catch (e) {}
			siteNotice.classList.add('site-notice-hide');
			setTimeout(function () {
				siteNotice.remove();
			}, 250);
		};
		if (noticeConfirm) {
			noticeConfirm.addEventListener('click', closeNotice);
		}
		// 点击遮罩也可关闭（同样记住）
		var noticeMask = siteNotice.querySelector('.site-notice-mask');
		if (noticeMask) {
			noticeMask.addEventListener('click', closeNotice);
		}
	}

	// 回到顶部按钮
	var backToTop = document.querySelector('.back-to-top');
	if (backToTop) {
		var showThreshold = 300;
		var onScroll = function () {
			var y = window.pageYOffset || document.documentElement.scrollTop;
			if (y > showThreshold) {
				backToTop.classList.add('back-to-top-on');
			} else {
				backToTop.classList.remove('back-to-top-on');
			}
		};
		window.addEventListener('scroll', onScroll, { passive: true });
		onScroll();

		backToTop.addEventListener('click', function () {
			window.scrollTo({ top: 0, behavior: 'smooth' });
		});
	}
})();
