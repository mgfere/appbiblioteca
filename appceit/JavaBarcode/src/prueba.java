import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.PreparedStatement;
import java.sql.SQLException;
import io.github.cdimascio.dotenv.Dotenv;

public class prueba {
    public static void main(String[] args) {
        Dotenv dotenv = Dotenv.configure()
                .directory("././") 
                .load();

        String host = dotenv.get("DB_URL");
        String usuario = dotenv.get("USUARIO");
        String password = dotenv.get("PASSWORD");
        String baseDeDatos = dotenv.get("DATABASE");


        if (host == null || usuario == null || password == null || baseDeDatos == null) {
            System.out.println("Error: Alguna variable de entorno no fue encontrada.");
            return;
        }

        String url = "jdbc:mysql://" + host + "/" + baseDeDatos;
        
        try (Connection conn = DriverManager.getConnection("jdbc:mysql://" + host + "/" + baseDeDatos, usuario, password);
                 PreparedStatement stmt = conn.prepareStatement("Select * from libros where CodigoDeBarras = 1")) {

                try (java.sql.ResultSet resultSet = stmt.executeQuery()) {
                    while (resultSet.next()) {
                        int id = resultSet.getInt("ID");
                        String nombre = resultSet.getString("Titulo");
                        System.out.println("ID: " + id + ", Titulo: " + nombre);
                    }
                }

            } catch (SQLException e) {
                System.err.println("ERROR_BD: " + e.getMessage());
                e.printStackTrace();
                System.exit(1);
            }

        try (Connection conn = DriverManager.getConnection(url, usuario, password)) {
            if (conn != null) {
                System.out.println("Conexión exitosa a la base de datos.");

            }
        } catch (SQLException e) {
            System.err.println("Error al conectar a la base de datos:");
            e.printStackTrace();
        }
        
    }
}