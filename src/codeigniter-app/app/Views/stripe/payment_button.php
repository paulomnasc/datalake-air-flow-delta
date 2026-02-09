<!-- src/codeigniter-app/app/Views/stripe/payment_button.php -->
<form id="stripe-payment-form" action="/stripe/create-checkout-session" method="POST">
    <input type="hidden" name="email" value="<?= esc($userEmail) ?>">
    <input type="hidden" name="price_id" value="<?= esc($priceId) ?>">
    <button type="submit" class="btn btn-primary">Comprar acesso ao curso</button>
</form>
<script>
    document.getElementById('stripe-payment-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const response = await fetch(form.action, {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.url) {
            window.location.href = data.url;
        } else {
            alert('Erro ao iniciar pagamento.');
        }
    });
</script>
