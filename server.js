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
let userStatus = {};
let activeUsersInChat = new Set();
let postLikes = {};
let totalLikes = 0;
let comments = {};

io.on('connection', (socket) => {
    console.log('A user connected: ' + socket.id);

    socket.on('is_active', (user) => {
        const { is_active, user_id } = user;
        // console.log(`User is active in chat: ${is_active}, user ID: ${user_id}`);
        if (is_active) {
            userStatus[user_id] = 'active';
            activeUsersInChat.add(user_id);
            console.log(`User ${user_id} marked as active. Active users: ${Array.from(activeUsersInChat)}`);
            io.emit('userStatusUpdate', { user_id, status: 'active' });
        } else {
            userStatus[user_id] = 'inactive';
            activeUsersInChat.delete(user_id);
            console.log(`User ${user_id} marked as inactive. Active users: ${Array.from(activeUsersInChat)}`);
            io.emit('userStatusUpdate', { user_id, status: 'inactive' });
        }

        // Emit the updated list of active users
        io.emit('activeUsers', Array.from(activeUsersInChat));
    });

    socket.on('like', (data) => {
        const { postId, userId } = data;
        if (!postLikes[postId]) {
            postLikes[postId] = new Set();
        }
        if (postLikes[postId].has(userId)) {
            postLikes[postId].delete(userId);
            totalLikes--;
            console.log(`User ${userId} unliked post ${postId} , TotalLikes: ${totalLikes}` );
        } else {
            postLikes[postId].add(userId);
            totalLikes++;
            console.log(`User ${userId} liked post ${postId}, TotalLikes: ${totalLikes}`);
        }
        const likeCount = postLikes[postId].size;
        io.emit('likeUpdate', { postId, likeCount, totalLikes });
    });
    socket.on('addComment', (data) => {
        const { newsfeedId, userId, comment, parentId = null } = data;
        const newComment = {
            id: socket.id + Date.now(), // Unique ID for the comment
            userId,
            comment,
            parentId,
            timestamp: new Date()
        };
        if (!comments[newsfeedId]) {
            comments[newsfeedId] = [];
        }
        comments[newsfeedId].push(newComment);
        console.log("New Comment Added:", newComment);
        io.emit('commentUpdate', { newsfeedId, comments: comments[newsfeedId] });
    });
    socket.on('addReply', (data) => {
        const { newsfeedId, parentId, userId, reply } = data;

        const newReply = {
            id: socket.id + Date.now(), // Unique ID for the reply
            userId,
            comment: reply,
            parentId,
            timestamp: new Date()
        };
        if (!comments[newsfeedId]) {
            comments[newsfeedId] = [];
        }
        comments[newsfeedId].push(newReply);
        console.log("New Reply Added:", newReply);
        io.emit('commentUpdate', { newsfeedId, comments: comments[newsfeedId] });
    });
    socket.on('message', (message) => {
      console.log(`Message : ${message}`);
      io.emit("message",message)
  });
    socket.on('joinRoom', (room) => {
        socket.join(room);
        console.log(`User ${socket.id} joined room: ${room.room}`);
        socket.to(room).emit('message', `User ${socket.id} has joined the room.`);
    });
    socket.on('leaveRoom', (room) => {
        socket.leave(room);
        console.log(`User ${socket.id} left room: ${room.room}`);
        socket.to(room).emit('message', `User ${socket.id} has left the room.`);
    });
    socket.on('roomMessage', ({ room, message }) => {
        console.log(`Message to room  ${room}: ${message}`);
        io.to(room).emit('roomMessage', { user: socket.id, message });
    });
    socket.on('disconnect', () => {
        console.log('User disconnected: ' + socket.id);

        for (const userId of activeUsersInChat) {
            userStatus[userId] = 'inactive';
            activeUsersInChat.delete(userId);
            console.log(`User ${userId} marked as inactive upon disconnection.`);
            io.emit('userStatusUpdate', { userId, status: 'inactive' });
        }
    });
});
server.listen(3000, "192.168.10.14", () => {
    console.log('Server running at http://192.168.10.14:3000');
});
