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
        post: function (url, params, callback) {
            return $.ajax({
                type: 'POST',
                url: url,
                data: params,
                success: callback,
                beforeSend: function (xhr, settings) {
                    function sameOrigin(url) {
                        var host = document.location.host;
                        var protocol = document.location.protocol;
                        var sr_origin = '//' + host;
                        var origin = protocol + sr_origin;
                        return (url == origin || url.slice(0, origin.length + 1) == origin + '/') ||
                            (url == sr_origin || url.slice(0, sr_origin.length + 1) == sr_origin + '/') ||
                            !(/^(\/\/|http:|https:).*/.test(url));
                    }
                    if (sameOrigin(settings.url)) {
                        xhr.setRequestHeader('X-Simple-Token', $.cookie('simpletoken'));
                    }
                }
            });
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
            var errorBlock = $('.errors-block:visible');
            var wasBlock = errorBlock.html();
            errorBlock.html('');

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
                            orderist.popup.alert(errorText);
                        }
                    }

                    wasBlock && errorBlock.fadeTo('fast', 0.5).fadeTo('fast', 1.0);

                    return false;
                }
            }

            return response.res;
        },
        setLoading: function(el, loading) {
            el = $(el);
            var btn = $('.btn-loading', el);

            $('span.loader', el).html($('.loader-img:first').clone());

            if (loading) {
                var wasLoading = el.hasClass('loading');
                if (wasLoading) return false;

                btn.attr('disabled', 'disabled');
                el.addClass('loading');
            } else {
                var wasLoading = el.hasClass('loading');
                if (!wasLoading) return false;

                btn.removeAttr('disabled');
                el.removeClass('loading');
            }

            return true;
        },
    },
    popup: {
        instance: null,
        onOpen: null,
        open: function(html) {
            if (orderist.popup.instance) {
                orderist.popup.onOpen = function () {
                    orderist.popup.doOpen(html);
                };
                orderist.popup.instance.modal('hide');
            } else {
                orderist.popup.doOpen(html);
            }
        },
        close: function() {
            orderist.popup.instance && orderist.popup.instance.modal('hide');
        },
        doOpen: function(html) {
            $('#modal-popup').html(html);
            orderist.popup.instance = $('#modal-popup').modal('show');
            orderist.popup.instance.on('hidden.bs.modal', function (e) {
                orderist.popup.instance.off('hidden.bs.modal');
                orderist.popup.instance = null;
                if (orderist.popup.onOpen) {
                    var onOpen = orderist.popup.onOpen;
                    orderist.popup.onOpen = null;
                    onOpen();
                }
            });
        },
        confirmCallback: null,
        confirm: function(text, no, yes, callback) {
            var block = $('<div id="modal-confirm"><div class="modal-dialog">'+
                            '<div class="modal-content">'+
                                '<div class="modal-header">'+
                                    '<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>'+
                                    '<h4 class="modal-title">Подтверждение</h4>'+
                                '</div>'+
                                '<div class="modal-body">'+
                                    '<h3 class="text"></h3>'+
                                '</div>'+
                                '<div class="modal-footer">'+
                                    '<button type="button" class="btn btn-primary no" onclick="orderist.popup.close(); orderist.popup.confirmCallback(0);">Отмена</button>'+
                                    '<button type="button" class="btn btn-danger yes" onclick="orderist.popup.close(); orderist.popup.confirmCallback(1);">ОК</button>'+
                                '</div>'+
                            '</div>'+
                        '</div></div>');

            $('.text', block).html(text);
            $('.no', block).html(no);
            $('.yes', block).html(yes);

            orderist.popup.confirmCallback = callback;
            orderist.popup.open(block.html());
        },
        alert: function(text) {
            var block = $('<div id="modal-alert">'+
                            '<div class="modal-dialog" id="order-create-popup">'+
                                '<div class="modal-content">'+
                                    '<div class="modal-header">'+
                                        '<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>'+
                                        '<h4 class="modal-title">Произошла ошибка</h4>'+
                                    '</div>'+
                                    '<div class="modal-body"></div>'+
                                    '<div class="modal-footer">'+
                                        '<button type="button" class="btn btn-primary" data-dismiss="modal">Закрыть</button>'+
                                    '</div>'+
                                '</div>'+
                            '</div>'+
                        '</div>');
            $('.modal-body', block).html(text);
            orderist.popup.open(block.html());
        }
    },
    user: {
        login: function (submit) {
            submit = submit || false;
            var data = {};
            var block = $('#user-login-popup');

            if (submit) {
                var data = orderist.core.formData($('form', block));
                data['submit'] = 1;

                if (!orderist.core.setLoading(block, true)) {
                    return false;
                }
            }

            orderist.core.post('/user/login/', data, function (response) {
                submit && orderist.core.setLoading(block, false);
                orderist.core.processResponse(response);
            });
        },
        signup: function (submit) {
            submit = submit || false;
            var data = {};
            var block = $('#user-signup-popup');

            if (submit) {
                var data = orderist.core.formData($('form', block));
                data['submit'] = 1;

                if (!orderist.core.setLoading(block, true)) {
                    return false;
                }
            }

            orderist.core.post('/user/signup/', data, function (response) {
                submit && orderist.core.setLoading(block, false);
                orderist.core.processResponse(response);
            });
        },
        addCash: function(amount) {
            orderist.core.post('/user/addcash/', {amount: amount}, function (response) {
                orderist.core.processResponse(response);
                orderist.user.reloadPayments();
            });
        },
        reloadCash: function() {
            orderist.core.post('/user/getcash/', {}, function (response) {
                orderist.core.processResponse(response);
            });
        },
        isLoading: false,
        isFinished: false,
        loadMoreUrl: '/user/getpaymentspage/',
        loadPayments: function(reload) {
            reload = reload || false;
            if (reload) orderist.user.isFinished = false;
            if (orderist.user.isLoading || orderist.user.isFinished) return;

            orderist.user.isLoading = true;

            var lastPaymentId = reload ? 0 : $('.user-payment-block:last').data('id');
            orderist.core.post(orderist.user.loadMoreUrl, {last_payment_id: lastPaymentId}, function (response) {
                if (reload) $('#user-payments-block tbody').html('');
                if (!response.data.has_next) orderist.user.isFinished = true;
                orderist.user.isLoading = false;
                response.data.html && $('#user-payments-block').show();
                $('#user-payments-block tbody').append(response.data.html);
            });
        },
        reloadPayments: function() {
            if ($('#user-payments-block').length) {
                orderist.user.loadPayments(true);
            }
        }
    },
    order: {
        createPopup: function () {
            orderist.core.post('/order/createpopup/', {}, function (response) {
                if (orderist.core.processResponse(response) && response.data.html) {
                    $('#order-create-popup input[name=order_price]').bind('keyup', function() {
                        var result = this.value.replace(/[^0-9\.]/g, '')
                        if (result != this.value) {
                            this.value = result;
                        }
                        if (this.value) {
                            var result = Number(this.value.toString().match(/^\d+(?:\.\d{0,2})?/));
                            if (this.value != result) {
                                this.value = result;
                            }
                        }
                    });
                    $('#order-create-popup input[name=order_price]').bind('input', function() {
                        var price = $(this).val().replace(/[^0-9\.]/g, '');
                        var popup = $('#order-create-popup');
                        var commission = $('.order-commission', popup).data('value');

                        price = price * 100;
                        var commissionPrice = Math.ceil(price * commission);
                        var executerPrice = (price - commissionPrice) / 100;
                        var realCommission = price == 0 ? (commission * 100) : Math.ceil(commissionPrice / price * 100);

                        $('.order-commission', popup).html(realCommission + '%');
                        $('.executer-price', popup).html(parseFloat(executerPrice.toFixed(2)));
                    });
                }
            });
        },
        create: function() {
            var popup = $('#order-create-popup');
            var footer = $('.modal-footer.create', popup);

            if (!orderist.core.setLoading(popup, true)) {
                return false;
            }

            orderist.core.post('/order/create/', orderist.core.formData($('form', popup)), function (response) {
                orderist.core.setLoading(popup, false);
                if (orderist.core.processResponse(response)) {
                    $('input,textarea', popup).attr('disabled', 'disabled');
                    footer.fadeOut('fast', function() {
                        footer.html($('.modal-footer.done', popup).html());
                        footer.fadeIn('fast');
                    });
                    orderist.order.reload();
                }
            });
        },
        execute: function (orderId) {
            var orderBlock = $('#order-'+orderId);
            if (!orderist.core.setLoading(orderBlock, true)) {
                return false;
            }

            orderist.core.post('/order/execute/', {order_id: orderId}, function (response) {
                orderist.core.setLoading(orderBlock, false);

                var disableBtn = function(block) {
                    block.addClass('disabled');
                    $('button', block).html('Заказ выполнен');
                    $('button', block).removeAttr('onclick');
                }

                if (response.data && response.data.order == 'disabled') {
                    disableBtn(orderBlock);
                }

                if (orderist.core.processResponse(response)) {
                    disableBtn(orderBlock);
                    $('.order-get-text', orderBlock).html('Вы получили:');
                }
            });
        },
        delete: function (orderId) {
            var orderBlock = $('#order-'+orderId);
            if (!orderist.core.setLoading(orderBlock, true)) {
                return false;
            }

            orderist.core.post('/order/delete/', {order_id: orderId}, function (response) {
                orderist.core.setLoading(orderBlock, false);

                if (orderist.core.processResponse(response)) {
                    orderBlock.fadeOut('fast');
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
        loadMore: function(reload) {
            if (reload) orderist.order.isFinished = false;
            if (orderist.order.isLoading || orderist.order.isFinished) return;

            orderist.order.isLoading = true;

            var lastOrderId = reload ? 0 : $('.order-block:last').data('id');
            orderist.core.post(orderist.order.loadMoreUrl, {last_order_id: lastOrderId}, function (response) {
                if (reload) $('#orders-block').html('');
                if (!response.data.has_next) orderist.order.isFinished = true;
                orderist.order.isLoading = false;
                $('#orders-block').append(response.data.html);
            });
        },
        reload: function() {
            if ($('#orders-block').length) {
                orderist.order.loadMore(true);
            }
        }
    }
};

$(document).ready(function () {
    var socket = io.connect('http://orderist.smdmitry.com:8080');
    socket.on('message', function (data) {
        console.log('socket data', data);

        if (data.type == 'cash') {
            orderist.user.reloadCash();
        } else if (data.type == 'order') {
            if (data.action == 'executed') {
                var block = $('#order-'+data.id);
                block.addClass('disabled');
                $('button', block).html('Заказ выполнен');
                $('button', block).removeAttr('onclick');
            } else if (data.action == 'deleted') {
                var block = $('#order-'+data.id);
                block.addClass('disabled');
                $('button', block).html('Заказ удалён');
                $('button', block).removeAttr('onclick');
            } else if (data.action == 'created') {
                // add new orders block
            }
        }
    });
});