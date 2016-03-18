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
    order: {
        createPopup: function () {
            orderist.core.post('/order/createpopup/', {}, function (response) {
                if (response.data.html) {
                    orderist.popup.open(response.data.html);
                } else {
                    orderist.core.notify('Ошибка');
                }
            });
        }
    }
};