import express from "express";
import mysql from "mysql";
import cors from "cors";
import dotenv from "dotenv";
import path from "path";

// Cargar las variables de entorno desde el archivo .env
dotenv.config({ path: path.resolve("../../.env") });

const app = express();
app.use(cors());
app.use(express.json());

const db = mysql.createConnection({
  host: process.env.HOST,
  user: process.env.USUARIO,
  password: process.env.PASSWORD,
  database: process.env.DATABASE,
});

db.connect((err) => {
  if (err) {
    console.error("Error al conectar la base de datos:", err);
    return;
  }
  console.log("Conectado a la base de datos MySQL.");
});

app.get("/libros", (req, res) => {
  const sql = "SELECT * FROM libros WHERE status = 'Activo'";
  db.query(sql, (err, result) => {
    if (err) throw err;
    res.json(result);
  });
});

app.listen(4000, () => {
  console.log("Servidor corriendo en el puerto 4000");
});
