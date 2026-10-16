<label for="assignment-url">Tautan penugasan (hanya untuk individu yang dipilih)</label>
<input readonly id="assignment-url" value="{{ $result['url'] }}" aria-describedby="copy-status">
<button type="button" id="copy-assignment-url" aria-describedby="copy-status">Salin tautan</button>
<p id="copy-status" role="status" aria-live="polite">Bagikan hanya kepada individu yang ditugaskan. Akses tetap memerlukan autentikasi dan otorisasi.</p>
<script>
const linkInput = document.getElementById('assignment-url');
const copyStatus = document.getElementById('copy-status');
document.getElementById('copy-assignment-url').addEventListener('click', async () => {
    try {
        await navigator.clipboard.writeText(linkInput.value);
        copyStatus.textContent = 'Tautan berhasil disalin.';
    } catch {
        linkInput.focus();
        linkInput.select();
        copyStatus.textContent = 'Tautan belum disalin. Salin secara manual dari kolom tautan yang dipilih.';
    }
});
window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
</script>
