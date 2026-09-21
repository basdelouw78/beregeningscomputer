import 'audio_service.dart';
import 'report_service.dart';
import 'storage_service.dart';

/// Eenvoudige, app-brede singletons. Voor de omvang van deze app is een
/// volwaardige state-managementoplossing niet nodig; de services zijn zelf
/// al staatloos/eenvoudig genoeg om direct door widgets te worden gebruikt.
class AppServices {
  AppServices._();
  static final AppServices instance = AppServices._();

  final StorageService opslag = StorageService();
  final ReportService verslag = ReportService();

  /// Eén [AudioService] per actieve live-sessie; wordt aangemaakt/vrijgegeven
  /// door [SessionScreen] zelf omdat de recorder per sessie leeft.
  AudioService nieuweAudioService() => AudioService();
}
