import 'package:flutter_test/flutter_test.dart';
import 'package:oogsignalen_app/models/eye_direction.dart';

void main() {
  group('interpreteerRichting (standaard profiel)', () {
    test('omhoog-links (beeld) = eigen rechts = visueel geconstrueerd', () {
      final interpretatie = interpreteerRichting(EyeDirection.upLeft);
      expect(interpretatie.code, 'Vc');
    });

    test('omhoog-rechts (beeld) = eigen links = visueel herinnerd', () {
      final interpretatie = interpreteerRichting(EyeDirection.upRight);
      expect(interpretatie.code, 'Ve');
    });

    test('omlaag-rechts (beeld) = eigen links = auditief digitaal', () {
      final interpretatie = interpreteerRichting(EyeDirection.downRight);
      expect(interpretatie.code, 'Ad');
    });

    test('omlaag-links (beeld) = eigen rechts = kinesthetisch', () {
      final interpretatie = interpreteerRichting(EyeDirection.downLeft);
      expect(interpretatie.code, 'K');
    });

    test('onbekend geeft altijd de onbekend-interpretatie', () {
      final interpretatie = interpreteerRichting(EyeDirection.onbekend);
      expect(interpretatie, DiltsInterpretatie.onbekend);
    });
  });

  group('interpreteerRichting (gespiegeld profiel)', () {
    test('spiegelt links/rechts t.o.v. het standaardprofiel', () {
      final standaard = interpreteerRichting(EyeDirection.upLeft);
      final gespiegeld = interpreteerRichting(
        EyeDirection.upLeft,
        profiel: DiltsProfiel.gespiegeld,
      );
      expect(gespiegeld.code, isNot(standaard.code));
      expect(gespiegeld.code, 'Ve');
    });
  });
}
