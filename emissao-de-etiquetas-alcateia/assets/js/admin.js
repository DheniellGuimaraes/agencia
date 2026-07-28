(function () {
	'use strict';

	function setupTrackingSendButton() {
		var codeInput = document.getElementById('alcateia_tracking_code');
		var sendButton = document.querySelector('.alcateia-send-now');

		if (!codeInput || !sendButton) {
			return;
		}

		function refreshButton() {
			var hasCode = codeInput.value.trim().length > 0;
			sendButton.disabled = !hasCode;
			sendButton.classList.toggle('alcateia-send-now-pulse', hasCode);
			sendButton.title = hasCode ? 'Clique para salvar e enviar o rastreio agora.' : 'Preencha o código de rastreio para enviar.';
		}

		codeInput.addEventListener('input', refreshButton);
		refreshButton();
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.alcateia-card').forEach(function (card) {
			card.addEventListener('mousemove', function (event) {
				var rect = card.getBoundingClientRect();
				card.style.setProperty('--alcateia-x', ((event.clientX - rect.left) / rect.width) * 100 + '%');
				card.style.setProperty('--alcateia-y', ((event.clientY - rect.top) / rect.height) * 100 + '%');
			});
		});

		setupTrackingSendButton();
	});
}());
