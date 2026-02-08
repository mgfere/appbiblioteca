import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.Statement;
import java.sql.ResultSet;

public class DATABASE {
    
    public static void main(String[] args) {
        Connection conn = null;
        try {
            // Datos de conexión - AJUSTA ESTOS VALORES
            String server = "ROY";  // Usar \\\\ para escape
            String dbName = "GestionUsuarios";
            String user = "SA";  // Cambia por tu usuario de SQL Server
            String password = "1234";  // Cambia por tu contraseña
            
            System.out.println("=== CONEXIÓN SQL SERVER ===");
            System.out.println("Servidor: " + server);
            System.out.println("Base de datos: " + dbName);
            System.out.println("Usuario: " + user);
            
            // URL de conexión - DIFERENTES OPCIONES
            String url = "jdbc:sqlserver://" + server + ";databaseName=" + dbName + 
                        ";encrypt=true;trustServerCertificate=true";
            
            System.out.println("URL: " + url);
            
            // 1. Cargar el driver manualmente (IMPORTANTE)
            System.out.println("Cargando driver...");
            Class.forName("com.microsoft.sqlserver.jdbc.SQLServerDriver");
            System.out.println("✓ Driver cargado correctamente");
            
            // 2. Establecer conexión
            System.out.println("Estableciendo conexión...");
            conn = DriverManager.getConnection(url, user, password);
            System.out.println("✓ ¡Conexión exitosa!");
            
            // 3. Probar la conexión con una consulta simple
            System.out.println("Probando consulta...");
            testConnection(conn);
            
        } catch (ClassNotFoundException e) {
            System.err.println("✗ ERROR: Driver no encontrado");
            System.err.println("Asegúrate de que mssql-jdbc-13.2.0.jre11.jar esté en el classpath");
            e.printStackTrace();
        } catch (Exception e) {
            System.err.println("✗ ERROR: " + e.getMessage());
            e.printStackTrace();
        } finally {
            if (conn != null) {
                try {
                    conn.close();
                    System.out.println("✓ Conexión cerrada correctamente");
                } catch (Exception e) {
                    e.printStackTrace();
                }
            }
        }
    }
    
    private static void testConnection(Connection conn) {
        try {
            Statement stmt = conn.createStatement();
            ResultSet rs = stmt.executeQuery("SELECT @@VERSION as version");
            
            if (rs.next()) {
                String version = rs.getString("version");
                System.out.println("✓ SQL Server version: " + version.split("\n")[0]);
            }
            
            // Verificar si existe la tabla Alumnos
            rs = stmt.executeQuery(
                "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'Alumnos'"
            );
            
            if (rs.next() && rs.getInt("count") > 0) {
                System.out.println("✓ Tabla 'Alumnos' encontrada");
                
                // Mostrar algunos datos
                ResultSet data = stmt.executeQuery("SELECT TOP 3 * FROM Alumnos");
                System.out.println("=== DATOS DE ALUMNOS ===");
                while (data.next()) {
                    System.out.println("Matrícula: " + data.getString("Matricula") + 
                                     " - Correo: " + data.getString("Correo"));
                }
                data.close();
            } else {
                System.out.println("ℹ Tabla 'Alumnos' no encontrada");
            }
            
            rs.close();
            stmt.close();
            
        } catch (Exception e) {
            System.err.println("Error en consulta: " + e.getMessage());
        }
    }
}