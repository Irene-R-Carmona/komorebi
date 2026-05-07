// eslint.config.js — configuración ESLint flat (v9+)
// Instalación: npm install --save-dev eslint @typescript-eslint/eslint-plugin @typescript-eslint/parser
export default [
  {
    // JS público (Alpine.js helpers)
    files: ['public/js/**/*.js'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'module',
      globals: {
        window: 'readonly',
        document: 'readonly',
        console: 'readonly',
        Alpine: 'readonly',
        fetch: 'readonly',
      },
    },
    rules: {
      'no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
      'no-console': 'off',
      eqeqeq: ['error', 'always'],
      'no-var': 'error',
      'prefer-const': 'error',
    },
  },
  {
    // Archivos ignorados
    ignores: [
      'node_modules/**',
      'vendor/**',
      'dist/**',
      'public/js/charts.min.js',
      'bootstrap/cache/**',
    ],
  },
];
