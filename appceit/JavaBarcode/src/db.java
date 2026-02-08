import java.sql.DriverManager;
import io.github.cdimascio.dotenv.Dotenv;
import java.sql.SQLException;
import java.io.File;
import java.sql.Connection;

public class db {
    public static void main(String[] args) {

        // Cargar variables .env
        String rutaEnv = "../../.env";

        Dotenv dotenv = null;
        try {
            dotenv = Dotenv.configure()
                    .filename(new File(rutaEnv).getName())
                    .directory(new File(rutaEnv).getParent())
                    .load();
        } catch (Exception e) {
            System.err.println("ERROR: No se pudo cargar el archivo .env. " + e.getMessage());
            e.printStackTrace();
            System.exit(1);
        }
        if (dotenv == null) {
            System.err.println("ERROR: No se pudo cargar el archivo .env.");
            System.exit(1);
        }

        String dbName = dotenv.get("DB_Gestion");
        String user = dotenv.get("DB_UID");
        String password = dotenv.get("DB_PWD");
        String server = dotenv.get("SERVERNAME");

        System.out.println("DB: " + dbName);
        System.out.println("User: " + user);

        String url = "jdbc:sqlserver://" + server + ";databaseName=" + dbName + ";encrypt=true;trustServerCertificate=true";

        try (Connection conn = DriverManager.getConnection(url, user, password)) {
            System.out.println("¡Conexión exitosa!");
        } catch (SQLException e) {
            System.err.println("ERROR: No se pudo conectar a la base de datos. " + e.getMessage());
            e.printStackTrace();
            System.exit(1);
        }

        // Ejemplo de consulta
        try (Connection conn = DriverManager.getConnection(url, user, password)) {
            String query = "SELECT * FROM Alumnos";
            System.out.println("Ejecutando consulta: " + query);
            try (java.sql.Statement stmt = conn.createStatement()) {
                try (java.sql.ResultSet rs = stmt.executeQuery(query)) {
                    while (rs.next()) {
                        System.out.println(rs.getString("Matricula") + " " + rs.getString("CorreoElectronico"));
                    }
                }
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }

    }

}
