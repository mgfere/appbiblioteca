
import com.google.zxing.BarcodeFormat;
import com.google.zxing.client.j2se.MatrixToImageWriter;
import com.google.zxing.common.BitMatrix;
import com.google.zxing.oned.Code128Writer;
import io.github.cdimascio.dotenv.Dotenv;
import javax.imageio.ImageIO;
import java.io.File;
import java.nio.file.FileSystems;
import java.nio.file.Path;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.PreparedStatement;
import java.sql.SQLException;

public class BarcodeGeneratorApp {

    public static void main(String[] args) {
        if (args.length < 4) {
            System.err.println("ERROR: Uso incorrecto. Se necesitan: código, título, ruta de imagen y ruta del .env.");
            System.exit(1);
        }

        String codigoLibro = args[0];
        String tituloLibro = args[1];
        String fullOutputPath = args[2];
        String rutaEnv = args[3];

        String codigoLimpio = codigoLibro.replaceAll("[^A-Za-z0-9_-]", "_").replaceAll("_+", "_").replaceAll("^_+|_+$", "");
        String tituloLimpio = tituloLibro.replaceAll("[^A-Za-z0-9_-]", "_").replaceAll("_+", "_").replaceAll("^_+|_+$", "");
        String outputFileName = tituloLimpio + "_" + codigoLimpio + ".png";

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

        String DB_HOST = dotenv.get("HOST");
        String DB_USER = dotenv.get("USUARIO");
        String DB_PASSWORD = dotenv.get("PASSWORD");
        String DB_NAME = dotenv.get("DATABASE");

        String DB_URL = "jdbc:mysql://" + DB_HOST + ":3306/" + DB_NAME;

        if (DB_HOST == null || DB_USER == null || DB_PASSWORD == null || DB_NAME == null) {
            System.err.println("ERROR: Alguna variable de entorno no fue encontrada en .env.");
            System.exit(1);
        }

        try {
            Code128Writer barcodeWriter = new Code128Writer();
            BitMatrix bitMatrix = barcodeWriter.encode(codigoLibro, BarcodeFormat.CODE_128, 300, 150);
            Path path = FileSystems.getDefault().getPath(fullOutputPath);
            ImageIO.write(MatrixToImageWriter.toBufferedImage(bitMatrix), "PNG", path.toFile());

            System.out.println("EXITO_BARCODE: Código de barras generado en " + path.toString());

            try (Connection conn = DriverManager.getConnection(DB_URL, DB_USER, DB_PASSWORD); PreparedStatement stmt = conn.prepareStatement("UPDATE libros SET CodigoDeBarras = 1, ImagenCodigoDeBarras = ? WHERE codigo = ?")) {

                stmt.setString(1, outputFileName);
                stmt.setString(2, codigoLibro);

                int rowsAffected = stmt.executeUpdate();
                if (rowsAffected > 0) {
                    System.out.println("EXITO_BD: Base de datos actualizada para el libro con código: " + codigoLibro);
                } else {
                    System.err.println("ADVERTENCIA_BD: No se pudo actualizar el libro con código: " + codigoLibro);
                    System.exit(1);
                }
            } catch (SQLException e) {
                System.err.println("ERROR_BD: " + e.getMessage());
                e.printStackTrace();
                System.exit(1);
            }
        } catch (Exception e) {
            System.err.println("ERROR_GENERAL: " + e.getMessage());
            e.printStackTrace();
            System.exit(1);
        }
    }
}
