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
  io.on('connection', (socket) => {
      console.log('A user connected: ' + socket.id);

      socket.on('message', (message) => {
            console.log('Message received:', message);
            io.emit('message', message);
        });
        socket.on('reply', (message) => {
            console.log('Message Reply:', message);
            io.emit('reply', message);
        });

  // Listen for events
  socket.on('disconnect', () => {
    console.log('User disconnected: ' + socket.id);
  });
});

server.listen(3000, "192.168.10.14", () => {
  console.log('Server running at http://192.168.10.14:3000');
});
