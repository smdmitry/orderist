var requestify = require('requestify');
var express = require('express');
var http = require('http');
var bodyParser = require('body-parser');

var app = express();
var server = http.createServer(app);
var io = require('socket.io').listen(server);

server.listen(8080);
app.use(bodyParser.json());

io.use(function(socket, next) {
    var data = socket.request;
    requestify.get('http://localhost/user/auth/', {
        dataType: 'json',
        headers: {
            'Cookie': data.headers["cookie"],
            'User-Agent': data.headers["user-agent"],
            'X-Requested-With': 'XMLHttpRequest'
        }
    }).then(function(response) {
        socket.handshake.user_id = 0;
        var body = response.getBody();
        if (body && body.res && body.data && body.data.user_id) {
            socket.handshake.user_id = body.data.user_id;
        }
        next();
    }, function(err) {
        socket.handshake.user_id = 0;
        next();
    });
});

app.get('/emit/:id/:token/:message/', function (req, res) {
    if (req.params.token != 'secret') {
        return res.json('auth');
    }
    delete req.params.token;

    var json = JSON.parse(req.params.message);
    if (req.params.id == 0) {
        io.emit('message', json);
    } else {
        io.to('uid'+req.params.id).emit('message', json);
    }

    res.json('ok');
});

io.sockets.on('connection', function (client) {
    var userId = client.handshake.user_id;
    if (userId) {
        client.join('uid'+userId);
    }
});