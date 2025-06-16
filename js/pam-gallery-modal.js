document.addEventListener('DOMContentLoaded', function () {
	const modal = document.getElementById('pamGalleryModal');
	const modalImg = document.getElementById('pamModalImage');
	const closeBtn = document.getElementById('pamModalClose');
	const prevBtn = document.getElementById('pamModalPrev');
	const nextBtn = document.getElementById('pamModalNext');
	const thumbs = document.querySelectorAll('.pam-gallery-thumb');

	let currentIndex = -1;
	const imageSources = Array.from(thumbs).map(img => img.getAttribute('data-full') || img.src);

	function openModal(index) {
		currentIndex = index;
		modalImg.src = imageSources[currentIndex];
		modal.classList.add('is-visible');

		// Show or hide navigation buttons based on number of images
		const hasMultipleImages = imageSources.length > 1;
		prevBtn.style.display = hasMultipleImages ? 'block' : 'none';
		nextBtn.style.display = hasMultipleImages ? 'block' : 'none';
	}

	function closeModal() {
		modal.classList.remove('is-visible');
	}

	function showPrev() {
		currentIndex = (currentIndex - 1 + imageSources.length) % imageSources.length;
		modalImg.src = imageSources[currentIndex];
	}

	function showNext() {
		currentIndex = (currentIndex + 1) % imageSources.length;
		modalImg.src = imageSources[currentIndex];
	}

	thumbs.forEach((thumb, index) => {
		thumb.addEventListener('click', () => openModal(index));
	});

	closeBtn.addEventListener('click', closeModal);
	prevBtn.addEventListener('click', showPrev);
	nextBtn.addEventListener('click', showNext);

	modal.addEventListener('click', function (e) {
		if (e.target === modal) {
			closeModal();
		}
	});

	document.addEventListener('keydown', function (e) {
		if (!modal.classList.contains('is-visible')) return;
		if (e.key === 'ArrowLeft' && imageSources.length > 1) showPrev();
		if (e.key === 'ArrowRight' && imageSources.length > 1) showNext();
		if (e.key === 'Escape') closeModal();
	});
});
