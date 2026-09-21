import 'package:flutter/material.dart';

import '../models/eye_direction.dart';

/// Referentieschema van het klassieke Dilts-model: een 3x3-raster met de
/// interpretatie per richting, zoals waargenomen door de camera (dus
/// gespiegeld t.o.v. het eigen perspectief van de persoon).
class DiltsDiagram extends StatelessWidget {
  final DiltsProfiel profiel;

  const DiltsDiagram({super.key, this.profiel = DiltsProfiel.standaard});

  static const _rijen = [
    [EyeDirection.upLeft, EyeDirection.upCenter, EyeDirection.upRight],
    [EyeDirection.midLeft, EyeDirection.center, EyeDirection.midRight],
    [EyeDirection.downLeft, EyeDirection.downCenter, EyeDirection.downRight],
  ];

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final rij in _rijen)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: Row(
              children: [
                for (final richting in rij)
                  Expanded(child: _cel(context, richting)),
              ],
            ),
          ),
        const SizedBox(height: 12),
        Text(
          diltsDisclaimer,
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Theme.of(context).colorScheme.error,
              ),
        ),
      ],
    );
  }

  Widget _cel(BuildContext context, EyeDirection richting) {
    final interpretatie = interpreteerRichting(richting, profiel: profiel);
    return Card(
      margin: const EdgeInsets.all(4),
      child: Padding(
        padding: const EdgeInsets.all(8),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              interpretatie.code,
              style: Theme.of(context)
                  .textTheme
                  .titleMedium
                  ?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 4),
            Text(
              interpretatie.titel,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }
}
