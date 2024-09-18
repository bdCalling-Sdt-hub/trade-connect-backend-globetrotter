// server.js

import express from 'express';
import http from 'http';
import { Server } from 'socket.io';

const app = express();
const server = http.createServer(app);
const io = new Server(server, {
  cors: {
    origin: "*",
    methods: ["GET", "POST"]
  }
});

app.use(express.json());

app.post('/trigger-event', (req, res) => {
  const { event, data } = req.body;
  console.log('Received event:', event, 'with data:', data);
  io.emit(event, data);
  res.status(200).send('Event triggered');
});

app.post('/trigger-like', (req, res) => {
    const { newsfeed_id, user_id } = req.body;
    console.log('Triggering like event for newsfeed:', newsfeed_id, 'by user:', user_id);
    io.emit('like', { newsfeed_id, user_id });
    res.status(200).send('Like event triggered');
  });

  app.post('/trigger-unlike', (req, res) => {
    const { newsfeed_id, user_id } = req.body;
    console.log('Triggering unlike event for newsfeed:', newsfeed_id, 'by user:', user_id);
    io.emit('unlike', { newsfeed_id, user_id });
    res.status(200).send('Unlike event triggered');
  });
io.on('connection', (socket) => {
  console.log('A user connected: ' + socket.id);

  // Listen for events
  socket.on('disconnect', () => {
    console.log('User disconnected: ' + socket.id);
  });
});

server.listen(3000, "192.168.10.14", () => {
  console.log('Server running at http://192.168.10.14:3000');
});
