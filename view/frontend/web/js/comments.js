/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 *
 * Comment form on Luma: sign-in state from the customer section, AJAX submit with the form key.
 */
define(['Magento_Customer/js/customer-data'], function (customerData) {
    'use strict';

    return function (config, root) {
        var form = root.querySelector('[data-role="form"]'),
            message = root.querySelector('[data-role="message"]'),
            login = root.querySelector('[data-role="login"]'),
            list = root.querySelector('[data-role="list"]'),
            submit = root.querySelector('[data-role="submit"]'),
            customer = customerData.get('customer');

        if (!form) {
            return;
        }

        function applyCustomer(data) {
            var loggedIn = !!(data && data.firstname),
                emailField = form.querySelector('[data-role="email-field"]'),
                nameInput = form.elements.author_name;

            form.hidden = !loggedIn && !config.guests;
            login.hidden = loggedIn || config.guests;
            emailField.hidden = loggedIn;
            form.elements.author_email.required = !loggedIn;
            if (loggedIn && !nameInput.value) {
                nameInput.value = data.fullname || data.firstname;
            }
        }

        function formKey() {
            var match = document.cookie.match(/(?:^|;\s*)form_key=([^;]+)/);

            return match ? decodeURIComponent(match[1]) : (form.elements.form_key ? form.elements.form_key.value : '');
        }

        function show(text, ok) {
            message.textContent = text;
            message.className = 'sw-blog-comment-form__message message ' + (ok ? 'success' : 'error');
        }

        function append(comment) {
            var item = document.createElement('li'),
                meta = document.createElement('p'),
                name = document.createElement('strong'),
                text = document.createElement('div'),
                empty = root.querySelector('[data-role="empty"]');

            item.className = 'sw-blog-comment';
            meta.className = 'sw-blog-comment__meta';
            name.textContent = comment.name;
            meta.appendChild(name);
            text.className = 'sw-blog-comment__text';
            text.style.whiteSpace = 'pre-line';
            text.textContent = comment.text;
            item.appendChild(meta);
            item.appendChild(text);
            list.appendChild(item);
            if (empty) {
                empty.hidden = true;
            }
        }

        applyCustomer(customer());
        customer.subscribe(applyCustomer);

        form.addEventListener('submit', function (event) {
            var data;

            event.preventDefault();
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            data = new FormData(form);
            data.set('form_key', formKey());
            submit.disabled = true;
            show(config.text.sending, true);
            fetch(config.url, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
            }).then(function (response) {
                return response.json();
            }).then(function (result) {
                show(result.message || config.text.error, !!result.success);
                if (result.success) {
                    if (result.published && result.comment && result.comment.text) {
                        append(result.comment);
                    }
                    form.elements.content.value = '';
                }
            }).catch(function () {
                show(config.text.error, false);
            }).finally(function () {
                submit.disabled = false;
                if (window.grecaptcha && form.querySelector('.g-recaptcha')) {
                    try {
                        window.grecaptcha.reset();
                    } catch (e) {
                        // widget not rendered yet
                    }
                }
            });
        });
    };
});
