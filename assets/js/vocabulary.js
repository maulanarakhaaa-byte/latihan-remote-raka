
document.querySelectorAll('.vocab-card').forEach(card => {
    card.addEventListener('click', () => card.classList.toggle('flipped'));
});
