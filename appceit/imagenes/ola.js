const cassandra = require('cassandra-driver');

const client = new cassandra.Client({
  contactPoints: ['127.0.0.1'],
  localDataCenter: 'datacenter1',
  keyspace: 'biblioteca_qr'
});

client.connect()
  .then(() => {
    console.log('Conectado a Cassandra');

    return client.execute('SELECT * FROM libros');
  })
  .then(result => {
    console.log('Libros:', result.rows);
  })
  .catch(err => {
    console.error('Error:', err);
  });
