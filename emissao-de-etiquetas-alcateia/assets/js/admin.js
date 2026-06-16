(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.alcateia-card').forEach(function (card) {
			card.addEventListener('mousemove', function (event) {
				var rect = card.getBoundingClientRect();
				card.style.setProperty('--alcateia-x', ((event.clientX - rect.left) / rect.width) * 100 + '%');
				card.style.setProperty('--alcateia-y', ((event.clientY - rect.top) / rect.height) * 100 + '%');
			});
		});
	});
}());
