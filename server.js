const express = require('express');
const app = express();
const PORT = 3000;

// Middleware to parse JSON data in requests
app.use(express.json());

// Files API router
const filesRouter = require('./routes/files');
app.use('/api/files', filesRouter);

// Sample data (Simulated Database)
let users = [
    { id: 1, name: "Alice" },
    { id: 2, name: "Bob" }
];

// GET: Fetch all users
app.get('/api/users', (req, res) => {
    res.json(users);
});

// POST: Create a new user
app.post('/api/users', (req, res) => {
    const newUser = {
        id: users.length + 1,
        name: req.body.name
    };
    users.push(newUser);
    res.status(201).json(newUser);
});

// Start the server
app.listen(PORT, () => {
    console.log(`Server running on http://localhost:${PORT}`);
});
