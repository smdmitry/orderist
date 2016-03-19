var orderist = {
    nav: {
        go: function (url) {
            document.location.href = url;
            return false;
        },
        refresh: function () {
            document.location.href = document.location.href;
        }
    },
    core: {
        get: function (url, params, callback) {
            return $.get(url, params, callback);
        },
        post: function (url, params, callback) {
            return $.post(url, params, callback);
        },
        notify: function (text) {
            $('#modal-alert .modal-body').html(text);
            orderist.popup.open($('#modal-alert').html());
        },
        formData: function(form) {
            var formArr = form.serializeArray();
            var data = {};

            $.map(formArr, function(n, i){
                data[n['name']] = n['value'];
            });

            return data;
        },
        processResponse: function(response) {
            if (response.base) {
                response.base.cash && $('.user-cash').html(response.base.cash);
                response.base.hold && $('.user-hold').html(response.base.hold);
            }
            if (response.data) {
                if (response.data.error && response.data.error == 'auth') {
                    orderist.user.login();
                    return false;
                }

                if (response.data.html) {
                    orderist.popup.open(response.data.html);
                    return true;
                } else if (response.data.redirect) {
                    orderist.nav.go(response.data.redirect);
                    return true;
                } else {
                    var errorBlock = $('.errors-block:visible');
                    var wasBlock = errorBlock.html();

                    if (response.data.errors && response.data.errors.length) {
                        errorBlock.html('');
                        $.each(response.data.errors, function(i, error) {
                            errorBlock.append('<div class="alert alert-dismissible alert-danger">'+error+'</div>')
                        });
                    } else {
                        var errorText = response.data.error || 'Ошибка!';
                        if (errorBlock.length) {
                            errorBlock.html('<div class="alert alert-dismissible alert-danger">' + errorText + '</div>');
                        } else {
                            orderist.core.notify(errorText);
                        }
                    }

                    wasBlock && errorBlock.fadeTo('fast', 0.5).fadeTo('fast', 1.0);

                    return false;
                }
            }

            return response.res;
        }
    },
    popup: {
        instance: null,
        open: function(html) {
            if (orderist.popup.instance) {
                orderist.popup.instance.modal('hide');
            }
            $('#modal-popup').html(html);
            orderist.popup.instance = $('#modal-popup').modal('toggle');
        }
    },
    user: {
        login: function (submit) {
            submit = submit || false;
            var data = {};

            if (submit) {
                var data = orderist.core.formData($('#user-login-popup form'));
                data['submit'] = 1;
            }

            orderist.core.post('/user/login/', data, function (response) {
                orderist.core.processResponse(response);
            });
        },
        signup: function (submit) {
            submit = submit || false;
            var data = {};

            if (submit) {
                var data = orderist.core.formData($('#user-signup-popup form'));
                data['submit'] = 1;
            }

            orderist.core.post('/user/signup/', data, function (response) {
                orderist.core.processResponse(response);
            });
        },
        addCash: function(amount) {
            orderist.core.post('/user/addcash/', {amount: amount}, function (response) {
                orderist.core.processResponse(response);
                orderist.user.reloadPayments();
            });
        },
        isLoading: false,
        isFinished: false,
        loadMoreUrl: '/user/getpaymentspage/',
        loadPayments: function() {
            if (orderist.user.isLoading || orderist.user.isFinished) return;

            orderist.user.isLoading = true;

            var lastPaymentId = $('.user-payment-block:last').data('id');
            orderist.core.post(orderist.user.loadMoreUrl, {last_payment_id: lastPaymentId}, function (response) {
                if (!response.has_next) orderist.user.isFinished = false;
                orderist.user.isLoading = false;
                $('#user-payments-block tbody').append(response.data.html);
            });
        },
        reloadPayments: function() {
            if ($('#user-payments-block').length) {
                $('#user-payments-block tbody').html('');
                orderist.user.loadPayments();
            }
        }
    },
    order: {
        createPopup: function () {
            orderist.core.post('/order/createpopup/', {}, function (response) {
                if (orderist.core.processResponse(response) && response.data.html) {
                    $('#order-create-popup input[name=order_price]').bind('keyup', function() {
                        this.value = this.value.replace(/[^0-9\.]/g, '');
                    });
                    $('#order-create-popup input[name=order_price]').bind('input', function() {
                        var price = $(this).val().replace(/[^0-9\.]/g, '');
                        var popup = $('#order-create-popup');
                        var commission = $('.order-commission', popup).data('value');
                        $('.executer-price', popup).html((price - price * commission).toFixed(2));
                    });
                }
            });
        },
        create: function() {
            var popup = $('#order-create-popup');
            orderist.core.post('/order/create/', orderist.core.formData($('form', popup)), function (response) {
                if (orderist.core.processResponse(response)) {
                    $('input', popup).attr('disabled', 'disabled');
                    $('.modal-footer', popup).hide();
                    $('.modal-footer.ok', popup).show();
                }
            });
        },
        execute: function (orderId) {
            orderist.core.post('/order/execute/', {order_id: orderId}, function (response) {
                if (response.data && response.data.order == 'disabled') {
                    $('#order-'+orderId).addClass('disabled');
                    $('#order-'+orderId+' button').html('Заказ выполнен');
                }

                if (orderist.core.processResponse(response)) {
                    $('#order-'+orderId).addClass('disabled');
                    $('#order-'+orderId+' .order-get-text').html('Вы получили:');
                    $('#order-'+orderId+' button').html('Заказ выполнен');
                }
            });
        },
        expand: function(id) {
            var block = $('#order-'+id);
            block.removeClass('expandable');
            $('.more', block).remove();
            $('.hyp', block).remove();
            $('.collapsed', block).show();
        },
        isLoading: false,
        isFinished: false,
        loadMoreUrl: '/index/getpage/',
        loadMore: function() {
            if (orderist.order.isLoading || orderist.order.isFinished) return;

            orderist.order.isLoading = true;

            var lastOrderId = $('.order-block:last').data('id');
            orderist.core.post(orderist.order.loadMoreUrl, {last_order_id: lastOrderId}, function (response) {
                if (!response.has_next) orderist.order.isFinished = false;
                orderist.order.isLoading = false;
                $('#orders-block').append(response.data.html);
            });
        }
    }
};