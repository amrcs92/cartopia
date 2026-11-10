import { Config } from '@remotion/cli/config';

// Remotion reads this from the folder holding package.json: run npx remotion studio, or
// npx remotion render Walkthrough, from showcase/.
Config.setEntryPoint('./video/src/index.ts');
Config.setPublicDir('./video/public');
Config.setVideoImageFormat('jpeg');
Config.setJpegQuality(92);
