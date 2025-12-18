# Architect's Ledger

## 2024-05-23 – [Legacy Infrastructure]
**Issue:**  Project relies on manual `require` and a custom autoloader. No package manager or tests.
**Constraint:**  Must maintain current functionality without aggressive rewrites.
**Decision:**  Introduce Composer primarily for dev-dependencies (testing, static analysis) and standard autoloading.
**Future Guidance:**  Migrate custom `.env` parsing and other utilities to standard packages (e.g., `vlucas/phpdotenv`) once Composer is established.
