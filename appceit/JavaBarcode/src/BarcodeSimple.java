import com.google.zxing.BarcodeFormat;
import com.google.zxing.client.j2se.MatrixToImageWriter;
import com.google.zxing.common.BitMatrix;
import com.google.zxing.oned.Code128Writer;
import javax.imageio.ImageIO;
import java.io.File;
import java.nio.file.FileSystems;
import java.nio.file.Path;

public class BarcodeSimple {
    public static void main(String[] args) {
        try {
            String dataToEncode = "1234567890"; // Información fija para el código de barras
            String outputFileName = "barcode_simple.png";
            String outputPath = "barcode_simple.png"; // Guardar en la carpeta actual

            // Generar el código de barras
            Code128Writer barcodeWriter = new Code128Writer();
            BitMatrix bitMatrix = barcodeWriter.encode(dataToEncode, BarcodeFormat.CODE_128, 300, 150);

            Path path = FileSystems.getDefault().getPath(outputPath);
            ImageIO.write(MatrixToImageWriter.toBufferedImage(bitMatrix), "PNG", path.toFile());

            System.out.println("Código de barras generado correctamente en: " + outputPath);
        } catch (Exception e) {
            System.err.println("Error al generar el código de barras: " + e.getMessage());
            e.printStackTrace();
        }
    }
}
