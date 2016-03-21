var requestify = require('requestify');
var express = require('express');
var http = require('http');
var bodyParser = require('body-parser')

var app = express();
var server = http.createServer(app);
var io = require('socket.io').listen(server);
server.listen(8080);

app.use(bodyParser.json());

//io.set('log level', 1);
io.set('authorization', function(data, callback) {
    data.tmptest = 1;
    callback(null, true);
    return;
    requestify.get('http://localhost/user/auth/', {
        dataType: 'json',
        headers: {
            'Cookie': data.headers["cookie"],
            'User-Agent': data.headers["user-agent"],
            'X-Requested-With': 'XMLHttpRequest'
        }
    }).then(function(response) {
        data.user_id = 0;
        var body = response.getBody();
        if (body && body.res && body.data && body.data.user_id) {
            data.user_id = body.data.user_id;
        }
        callback(null, 'test');
    }, function(err) {
        data.user_id = 0;
        callback(null, true);
    });
});

app.get('/emit/:id/:token/:message/', function (req, res) {
    if (req.params.token != 'secret') {
        return res.json('auth');
    }
    delete req.params.token;

    if (req.params.id == 0) {
        io.emit('message', req.params.message);
    } else {
        io.sockets.emit('message', req.params.message);
    }

    res.json('ok');
});

io.sockets.on('connection', function (client) {
    //var userId = client.handshake.user_id;

    /*client.on('message', function (message) {
        try {
            client.emit('message', message);
            client.broadcast.emit('message', message);
        } catch (e) {
            console.log(e);
            client.disconnect();
        }
    });*/
});