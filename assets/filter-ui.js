// Native selects stay keyboard friendly; long choices also appear in full below the field.
document.querySelectorAll('.sena-filter-field select').forEach(select => {
    const detail = document.createElement('span');
    detail.className = 'sena-filter-selection';
    detail.setAttribute('aria-hidden', 'true');
    select.after(detail);
    const update = () => {
        const text = select.selectedOptions[0]?.textContent.trim() || '';
        select.title = text;
        detail.hidden = !select.value || text.length <= 24;
        detail.textContent = detail.hidden ? '' : text;
    };
    update();
    select.addEventListener('change', update);
});
