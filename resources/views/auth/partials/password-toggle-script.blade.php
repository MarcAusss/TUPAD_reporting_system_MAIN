{{-- Show / hide toggle for password inputs marked with data-toggle-password="<input id>". --}}
<script>
    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.dataset.togglePassword);

            if (! input) {
                return;
            }

            var reveal = input.type === 'password';

            input.type = reveal ? 'text' : 'password';
            button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
            button.classList.toggle('text-[#063b86]', reveal);
        });
    });
</script>
