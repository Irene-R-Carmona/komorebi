# Focus States Audit

## Overview

This document audits focus state accessibility across Komorebi Café UI components.

## Requirements

- All interactive elements must have a visible focus indicator (WCAG 2.1 AA, criterion 2.4.7).
- Focus rings must have a minimum contrast ratio of 3:1 against adjacent colours.

## Components Reviewed

| Component       | Status  | Notes                          |
|-----------------|---------|--------------------------------|
| Navigation links | ✅ Pass | Visible outline applied        |
| Buttons          | ✅ Pass | Custom focus ring with offset  |
| Form inputs      | ✅ Pass | Border + shadow on focus       |
| Skip link        | ✅ Pass | Keyboard-only visible element  |
| Modal dialogs    | ✅ Pass | Focus trapped inside modal     |

## Last Reviewed

2025-01-01
