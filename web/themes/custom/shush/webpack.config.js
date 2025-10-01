const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = {
  entry: {
    main: ['./js/script.js', './sass/style.scss'], // bundle JS and SCSS
  },
  output: {
    path: path.resolve(__dirname, 'dist'), // output folder
    filename: 'js/[name].js', // JS output
    clean: true,
  },
  module: {
    rules: [
      // SCSS -> CSS
      {
        test: /\.scss$/i,
        use: [
          MiniCssExtractPlugin.loader,
          {
            loader: 'css-loader',
            options: { url: true, sourceMap: true },
          },
          {
            loader: 'postcss-loader',
            options: {
              postcssOptions: { plugins: [require('autoprefixer')] },
              sourceMap: true,
            },
          },
          { loader: 'sass-loader', options: { sourceMap: true } },
        ],
      },

      // Images
      {
        test: /\.(png|jpe?g|gif|svg)$/i,
        type: 'asset/resource',
        generator: { filename: 'images/[name][ext]' },
      },
    ],
  },
  plugins: [
    new MiniCssExtractPlugin({
      filename: 'css/[name].css', // CSS output
    }),
  ],
  resolve: {
    alias: {
      Images: path.resolve(__dirname, 'images'),
    },
  },
  devtool: 'source-map',
  mode: 'development',
};
