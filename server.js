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

    // Active status
    socket.on('is_active', (user) => {
      console.log(`User is active in chat : ${user.is_active}`);
      io.emit("is_active",user)
    });

    // message
    socket.on('message', (message) => {
      console.log(`Message : ${message}`);
      io.emit("message",message)
  });
    // Join a dynamic room
    socket.on('joinRoom', (room) => {
        socket.join(room);
        console.log(`User ${socket.id} joined room: ${room.room}`);
        socket.to(room).emit('message', `User ${socket.id} has joined the room.`);
    });

    // Leave a room
    socket.on('leaveRoom', (room) => {
        socket.leave(room);
        console.log(`User ${socket.id} left room: ${room.room}`);
        socket.to(room).emit('message', `User ${socket.id} has left the room.`);
    });

    // Handle sending messages to a specific room
    socket.on('roomMessage', ({ room, message }) => {
        console.log(`Message to room  ${room}: ${message}`);
        io.to(room).emit('roomMessage', { user: socket.id, message });
    });

    // Handle disconnection
    socket.on('disconnect', () => {
        console.log('User disconnected: ' + socket.id);
    });
});

server.listen(3000, "192.168.10.14", () => {
    console.log('Server running at http://192.168.10.14:3000');
});
