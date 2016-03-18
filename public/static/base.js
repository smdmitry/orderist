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
            alert(text);
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

            return false;
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
                        var comission = $('.order-comission', popup).data('value');
                        $('.executer-price', popup).html((price - price * comission).toFixed(2));
                    });
                }
            });
        },
        create: function() {
            orderist.core.post('/order/create/', orderist.core.formData($('form', popup)), function (response) {

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