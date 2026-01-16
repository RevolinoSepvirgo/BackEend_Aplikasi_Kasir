const express = require('express');
const { DataTypes } = require('sequelize');
const sequelize = require('./database');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const cors = require('cors');

const app = express();
app.use(cors());
app.use(express.json());

const JWT_SECRET = process.env.JWT_SECRET || 'rahasia_kasir_123';

/* =========================
   1. MODEL USER
========================= */
const User = sequelize.define('User', {
  username: { type: DataTypes.STRING, allowNull: false },
  email: { type: DataTypes.STRING, allowNull: false, unique: true },
  password: { type: DataTypes.STRING, allowNull: false },
  nama_toko: { type: DataTypes.STRING, allowNull: false },
  role: {
    type: DataTypes.ENUM('admin', 'toko'),
    defaultValue: 'toko'
  }
});

// Health Check Endpoint
app.get('/', (req, res) => {
    res.json({ 
        status: "API Ready", 
        service: "User Service (Node.js)",
        database: "PostgreSQL Connected"
    });
});

// Sync DB
sequelize.sync({ alter: true }).then(async () => {
  console.log('✅ Postgres Database synced');

  // Buat admin default jika belum ada
  try {
    const adminExists = await User.findOne({
      where: { email: 'admin@kasir.com' }
    });

    if (!adminExists) {
      const hashedPassword = await bcrypt.hash('admin123456', 10);
      await User.create({
        username: 'admin',
        email: 'admin@kasir.com',
        password: hashedPassword,
        nama_toko: 'Kasir Admin',
        role: 'admin'
      });
      console.log('✅ Admin default berhasil dibuat');
      console.log('📧 Email: admin@kasir.com');
      console.log('🔑 Password: admin123456');
    } else if (adminExists.role !== 'admin') {
      // Jika ada tapi bukan admin, ubah menjadi admin
      await adminExists.update({ role: 'admin' });
      console.log('✅ User diubah menjadi admin');
    } else {
      console.log('✅ Admin sudah ada');
    }
  } catch (err) {
    console.error('⚠️ Error saat membuat admin:', err.message);
  }
});

/* =========================
   2. MIDDLEWARE
========================= */
const authenticate = (req, res, next) => {
  const authHeader = req.headers['authorization'];
  const token = authHeader && authHeader.split(' ')[1];

  if (!token)
    return res.status(401).json({ error: 'Silakan login terlebih dahulu' });

  try {
    req.user = jwt.verify(token, JWT_SECRET);
    next();
  } catch {
    res.status(401).json({ error: 'Token tidak valid' });
  }
};

const adminOnly = (req, res, next) => {
  if (req.user.role !== 'admin') {
    return res.status(403).json({ error: 'Akses admin saja' });
  }
  next();
};

/* =========================
   3. AUTH
========================= */

// REGISTER → USER TOKO
app.post('/users/register', async (req, res) => {
  try {
    const { username, email, password, nama_toko } = req.body;

    const hashedPassword = await bcrypt.hash(password, 10);

    await User.create({
      username,
      email,
      password: hashedPassword,
      nama_toko,
      role: 'toko'
    });

    res.status(201).json({
      message: `Registrasi berhasil untuk Toko: ${nama_toko}`
    });
  } catch (err) {
    res.status(400).json({
      error: 'Email atau nama toko sudah digunakan'
    });
  }
});

// LOGIN (ADMIN & TOKO)
app.post('/users/login', async (req, res) => {
  const { email, password } = req.body;

  const user = await User.findOne({ where: { email } });
  if (!user || !(await bcrypt.compare(password, user.password))) {
    return res.status(401).json({ error: 'Email atau password salah' });
  }

  const token = jwt.sign(
    {
      id: user.id,
      username: user.username,
      nama_toko: user.nama_toko,
      role: user.role
    },
    JWT_SECRET,
    { expiresIn: '24h' }
  );

  res.json({
    token,
    role: user.role,
    nama_toko: user.nama_toko
  });
});

/* =========================
   4. USER TOKO
========================= */

// PROFIL SAYA
app.get('/users/me', authenticate, async (req, res) => {
  const user = await User.findByPk(req.user.id, {
    attributes: ['id', 'username', 'email', 'nama_toko', 'role']
  });
  res.json(user);
});

/* =========================
   5. USER LIST
========================= */

// ADMIN → semua user
// USER TOKO → hanya tokonya sendiri
app.get('/users', authenticate, async (req, res) => {
  const where =
    req.user.role === 'admin'
      ? {}
      : { nama_toko: req.user.nama_toko };

  const users = await User.findAll({
    where,
    attributes: ['id', 'username', 'email', 'nama_toko', 'role']
  });

  res.json(users);
});

// DETAIL USER
app.get('/users/:id', authenticate, async (req, res) => {
  const user = await User.findByPk(req.params.id, {
    attributes: ['id', 'username', 'email', 'nama_toko', 'role']
  });

  if (!user) {
    return res.status(404).json({ error: 'User tidak ditemukan' });
  }

  if (
    req.user.role !== 'admin' &&
    user.nama_toko !== req.user.nama_toko
  ) {
    return res.status(403).json({ error: 'Akses ditolak' });
  }

  res.json(user);
});

/* =========================
   6. CRUD USER
========================= */

// CREATE USER TOKO (ADMIN SAJA)
// request body: { username, email, password, nama_toko, role? }
// role opsional, default: 'toko', bisa: 'admin' atau 'toko'
app.post('/users', authenticate, adminOnly, async (req, res) => {
  const { username, email, password, nama_toko, role = 'toko' } = req.body;

  // Validasi role hanya admin dan toko
  if (!['admin', 'toko'].includes(role)) {
    return res.status(400).json({ error: 'Role hanya boleh "admin" atau "toko"' });
  }

  const hashedPassword = await bcrypt.hash(password, 10);

  const user = await User.create({
    username,
    email,
    password: hashedPassword,
    nama_toko,
    role
  });

  res.status(201).json({
    message: `User ${role} berhasil dibuat`,
    data: {
      id: user.id,
      username: user.username,
      email: user.email,
      nama_toko: user.nama_toko,
      role: user.role
    }
  });
});

// UPDATE USER
// ADMIN → semua user
// USER TOKO → hanya dirinya sendiri
app.put('/users/:id', authenticate, async (req, res) => {
  const user = await User.findByPk(req.params.id);
  if (!user) {
    return res.status(404).json({ error: 'User tidak ditemukan' });
  }

  if (
    req.user.role !== 'admin' &&
    req.user.id !== user.id
  ) {
    return res.status(403).json({ error: 'Akses ditolak' });
  }

  const data = { ...req.body };

  if (data.password) {
    data.password = await bcrypt.hash(data.password, 10);
  }

  await user.update(data);
  res.json({ message: 'User berhasil diperbarui' });
});

// DELETE USER (ADMIN SAJA)
app.delete('/users/:id', authenticate, adminOnly, async (req, res) => {
  const user = await User.findByPk(req.params.id);
  if (!user) {
    return res.status(404).json({ error: 'User tidak ditemukan' });
  }

  await user.destroy();
  res.json({ message: 'User berhasil dihapus' });
});

// PROMOTE/CHANGE USER ROLE (ADMIN SAJA)
app.patch('/users/:id/role', authenticate, adminOnly, async (req, res) => {
  const { role } = req.body;

  if (!['admin', 'toko'].includes(role)) {
    return res.status(400).json({ error: 'Role hanya boleh "admin" atau "toko"' });
  }

  const user = await User.findByPk(req.params.id);
  if (!user) {
    return res.status(404).json({ error: 'User tidak ditemukan' });
  }

  await user.update({ role });
  res.json({
    message: `User role berhasil diubah menjadi ${role}`,
    data: {
      id: user.id,
      username: user.username,
      role: user.role
    }
  });
});

/* =========================
   7. SERVER
========================= */
const PORT = 3000;
app.listen(PORT, '0.0.0.0', () => {
  console.log(`🚀 User Service running on port ${PORT}`);
});
