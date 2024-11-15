import express from 'express';
import { createServer } from 'http';
import { Server } from 'socket.io';

const app = express();
const server = createServer(app);
const io = new Server(server, {
    cors: {
        origin: '*',
    },
});

const users = {};

// const groups = {};

io.on('connection', (socket) => {
    console.log(`User connected: ${socket.id}`);
    // Listen for 'login' event and store user with socket ID
    socket.on('login', ({id}) => {
        users[id] = socket.id
        console.log(`User logged in: ${id}`);
        io.emit("login", socket.id)
        console.log("users : ",users)
    });

    // Handle private messages
    socket.on('message', ({id , message}) => {
        console.log(`Sending private message to User ${id} to ${message}`);
        if (users[id]) {

            console.log("users : ",users)
            io.to(users[id]).emit('message', {
                senderId: id,
                message
            });
            io.to(socket.id).emit('message', {
                senderId: id,
                message
            });
            console.log(users[id])
        } else {
            console.log(`User ${id} not found or offline.`);
        }
    });
    // Join a user to a group
    socket.on('join_group', ({ userId, groupId }) => {
        socket.join(groupId)
        // Add user to group
        console.log(`User ${userId} joined group ${groupId}`);
        io.to(groupId).emit("join_group", `${userId + "this user is joined group" + groupId}`)
    });
    // Handle group messages
    socket.on('group_message', ({ groupId, senderId, message }) => {
        socket.join(groupId)

        io.to(groupId).emit('group_message', { senderId, message });
        console.log(`Message in group ${groupId} from ${senderId}: ${message}`);

    });
    // Handle user disconnection and clean up
    socket.on('disconnect', () => {
        console.log(`User disconnected: ${socket.id}`);
        for (const [id] of Object.entries(users)) {
            console.log("des : user id ", id)
            if (id) {
                delete users[id];
                break;
            }
        }
        console.log(users)
    });
});
const PORT = 3000;
server.listen(PORT, () => {
    console.log(`Socket.IO server running at http://192.168.12.160:${PORT}`);
});
