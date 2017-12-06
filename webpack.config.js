let webpack = require('webpack');
let path = require('path');
const ExtractTextPlugin = require('extract-text-webpack-plugin');

module.exports = {

  entry: {
    sell_media: ['./src/js/app.js', './src/js/public.js', './node_modules/macy/dist/macy.js', './src/sass/public.scss', './node_modules/bulma/bulma.sass'],
    sell_media_admin: ['./src/js/admin.js', './src/sass/admin.scss', './src/sass/admin-price-listings.scss'],
    sell_media_admin_price_listings: ['./node_modules/parsleyjs/dist/parsley.js', './src/js/admin-price-listings.js'],
    sell_media_admin_media_uploader: ['./src/js/admin-media-uploader.js'],
  },
  output: {
    path: path.join(__dirname, 'dist'),
    filename: 'js/[name].js',
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /(node_modules|bower_components)/,
        use: {
          loader: 'babel-loader',
        },
      },
      {
        test: /\.vue$/,
        loader: 'vue-loader',
        options: {
          loaders: {

            'sass': [
              'vue-style-loader',
              'css-loader',
            ],

          },

        },
      },
      {
        test: /\.css$/,
        use: ExtractTextPlugin.extract({
          fallback: 'style-loader',
          use: 'css-loader',
        }),
      },
      { // sass / scss loader for webpack
        test: /\.(sass|scss)$/,
        use: ExtractTextPlugin.extract({
          fallback: 'style-loader',
          use: ['css-loader','sass-loader']
        })
      },
      {
        test: /\.(png|jpg|gif|svg)$/,
        loader: 'file-loader',
        options: {
          name: 'images/[name].[ext]?[hash]',
          publicPath: '../',
        }
      }
    ],
  },
  plugins: [
    new ExtractTextPlugin({
      filename: 'css/[name].css',
      allChunks: true,
    }),
  ],
  resolve: {
    alias: {
      'vue$': 'vue/dist/vue.esm.js'
    },
    extensions: ['*', '.js', '.vue', '.json']
  },
  devServer: {
    historyApiFallback: true,
    noInfo: true,
    headers: {
      'Access-Control-Allow-Origin': '*',
    },
    proxy: {
      "**": "http://sell-media.local"
    }
  },
  performance: {
    hints: false
  },
  externals: {
    "jquery": "jQuery" // loaded external to webpack and vue
  },
  devtool: '#eval-source-map'
}

if (process.env.NODE_ENV === 'production') {
  module.exports.devtool = '#source-map'
  // http://vue-loader.vuejs.org/en/workflow/production.html
  module.exports.plugins = (module.exports.plugins || []).concat([
    new webpack.DefinePlugin({
      'process.env': {
        NODE_ENV: '"production"'
      }
    }),
    new webpack.optimize.UglifyJsPlugin({
      sourceMap: true,
      compress: {
        warnings: false
      }
    }),
    new webpack.LoaderOptionsPlugin({
      minimize: true
    })
  ])
}