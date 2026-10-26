# Backend AI Tooling

This file is the index for AI-assisted development configuration. These files are for developer tooling; they are not required to run the Laravel application.

## Laravel Boost

- `composer.json` and `composer.lock` install Laravel Boost as a development dependency.
- `boost.json` selects the Boost integrations and skills used by this backend.
- `.mcp.json` exposes Laravel Boost as an MCP server by running `php artisan boost:mcp` from `backend/`.

## Agent instructions and skills

- `AGENTS.md` contains the shared backend instructions used by compatible coding agents.
- `CLAUDE.md` is the Claude Code instructions file; it currently matches `AGENTS.md`.
- `.claude/` contains Boost-generated skills and supporting references for Claude Code.
- `.kiro/` contains the corresponding Kiro skills and the Kiro MCP server settings.

Keep tool-specific files at these conventional paths so each integration can discover them. Update this index when adding or removing an AI tool integration. Do not put credentials or `.env` values in these files.
